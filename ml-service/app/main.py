from functools import lru_cache
from pathlib import Path
import re
from typing import Any, Dict, List, Set

import numpy as np
import pandas as pd
from fastapi import FastAPI
from pydantic import BaseModel, Field
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity
from fastapi.openapi.docs import get_swagger_ui_html


app = FastAPI(docs_url=None, redoc_url=None, title="SmartForum Recommendation Service", version="2.1.0")

DATASET_PATH = Path(__file__).resolve().parents[1] / "data" / "student_learning_interaction_dataset.csv"

# A candidate must clear at least one semantic gate to count as "related".
MIN_PROFILE_SIMILARITY = 0.08
MIN_ENGAGED_TOPIC_SIMILARITY = 0.10
MIN_SHARED_KEYWORDS = 1
UNJOINED_GROUP_BOOST = 0.05

ENGLISH_STOP_WORDS = {
    "a", "an", "and", "are", "as", "at", "be", "by", "for", "from", "has", "have", "in", "into",
    "is", "it", "of", "on", "or", "that", "the", "their", "this", "to", "was", "were", "will",
    "with", "you", "your", "about", "can", "how", "our", "what", "when", "where", "which", "who",
    "why", "all", "any", "but", "not", "one", "out", "use", "using", "used", "via", "new", "get",
}


class RecommendationRequest(BaseModel):
    user_id: int
    topics: List[Dict[str, Any]] | None = None
    history: List[Dict[str, Any]] | None = None
    member_group_ids: List[int] = Field(default_factory=list)
    engaged_topic_ids: List[int] = Field(default_factory=list)
    engaged_group_ids: List[int] = Field(default_factory=list)
    limit: int = 5


class RecommendationResponse(BaseModel):
    user_id: int
    recommendations: List[Dict[str, Any]]


@lru_cache(maxsize=1)
def load_interaction_dataset() -> pd.DataFrame:
    if DATASET_PATH.exists():
        return pd.read_csv(DATASET_PATH)
    return pd.DataFrame()


def normalize_text(value: Any) -> str:
    if value is None:
        return ""
    text = str(value).strip().lower()
    text = re.sub(r"[^\w\s]", " ", text)
    text = re.sub(r"\bwater\s+fall\b", "waterfall", text)
    text = re.sub(r"\s+", " ", text).strip()
    return text


def build_topic_text(topic: Dict[str, Any]) -> str:
    title = normalize_text(topic.get("title") or topic.get("Title") or topic.get("topic_title"))
    description = normalize_text(
        topic.get("description") or topic.get("Description") or topic.get("Topic_Description")
    )
    return f"{title} {description}".strip()


def build_interest_text(item: Dict[str, Any]) -> str:
    title = normalize_text(item.get("title") or item.get("topic_title"))
    description = normalize_text(item.get("description") or item.get("topic_description"))
    return f"{title} {description}".strip()


def extract_keywords(text: str) -> Set[str]:
    tokens = set(re.findall(r"[a-z0-9]{3,}", normalize_text(text)))
    return {token for token in tokens if token not in ENGLISH_STOP_WORDS}


def shared_keyword_count(left: str, right: str) -> int:
    left_keywords = extract_keywords(left)
    right_keywords = extract_keywords(right)
    overlap = left_keywords & right_keywords

    # Treat split spellings like "water fall" as "waterfall" when comparing titles.
    left_joined = normalize_text(left).replace(" ", "")
    right_joined = normalize_text(right).replace(" ", "")
    if len(left_joined) >= 4 and len(right_joined) >= 4:
        if left_joined in right_joined or right_joined in left_joined:
            overlap.add("phrase_match")

    return len(overlap)


def topic_id_from(item: Dict[str, Any]) -> int | None:
    raw_id = item.get("topic_id") or item.get("id") or item.get("Topic_ID")
    if raw_id is None:
        return None
    try:
        return int(raw_id)
    except (TypeError, ValueError):
        return None


def group_id_from(topic: Dict[str, Any]) -> int | None:
    raw_id = topic.get("group_id") or topic.get("Group_ID")
    if raw_id is None:
        return None
    try:
        return int(raw_id)
    except (TypeError, ValueError):
        return None


def aggregate_engagement_history(history: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
    """Collapse repeated interactions into one interest signal per engaged topic."""
    aggregated: Dict[int, Dict[str, Any]] = {}

    for item in history or []:
        topic_id = topic_id_from(item)
        if topic_id is None:
            continue

        weight = float(item.get("engagement_score", 0.2) or 0.2)
        title = item.get("title") or item.get("topic_title") or ""
        description = item.get("description") or item.get("topic_description") or ""

        if topic_id not in aggregated:
            aggregated[topic_id] = {
                "topic_id": topic_id,
                "title": title,
                "description": description,
                "engagement_score": weight,
            }
            continue

        existing = aggregated[topic_id]
        existing["engagement_score"] = float(existing["engagement_score"]) + weight
        if not existing.get("title") and title:
            existing["title"] = title
        if not existing.get("description") and description:
            existing["description"] = description

    return list(aggregated.values())


def collect_engaged_topic_ids(
    history: List[Dict[str, Any]],
    explicit_ids: List[int] | None = None,
) -> set[int]:
    engaged_ids = {int(topic_id) for topic_id in (explicit_ids or []) if topic_id is not None}

    for item in history or []:
        engagement_type = normalize_text(item.get("engagement_type") or "")
        if engagement_type not in {"view", "post", "reply"}:
            continue
        topic_id = topic_id_from(item)
        if topic_id is not None:
            engaged_ids.add(topic_id)

    return engaged_ids


def build_interest_profile(interest_history: List[Dict[str, Any]]) -> Dict[str, Any]:
    interest_texts = [build_interest_text(item) for item in interest_history]
    interest_keywords: Set[str] = set()
    for text in interest_texts:
        interest_keywords.update(extract_keywords(text))

    return {
        "items": interest_history,
        "texts": interest_texts,
        "keywords": interest_keywords,
        "corpus": " ".join(text for text in interest_texts if text).strip(),
    }


def is_related_to_interests(
    candidate_text: str,
    interest_profile: Dict[str, Any],
    profile_similarity: float,
    engaged_similarities: List[float],
) -> bool:
    """Return True only when the candidate is genuinely related to user interests."""
    if not interest_profile["corpus"]:
        return False

    best_engaged_similarity = max(engaged_similarities) if engaged_similarities else 0.0
    best_pairwise_keywords = max(
        shared_keyword_count(candidate_text, interest_text)
        for interest_text in interest_profile["texts"]
    ) if interest_profile["texts"] else 0

    if best_pairwise_keywords >= 2:
        return True
    if best_engaged_similarity >= MIN_ENGAGED_TOPIC_SIMILARITY:
        return True
    if profile_similarity >= MIN_PROFILE_SIMILARITY and best_pairwise_keywords >= 1:
        return True

    return False


def recommend_topics(
    topics: List[Dict[str, Any]],
    history: List[Dict[str, Any]],
    limit: int = 5,
    member_group_ids: List[int] | None = None,
    engaged_topic_ids: List[int] | None = None,
    engaged_group_ids: List[int] | None = None,
) -> List[Dict[str, Any]]:
    """
    Cross-group discovery recommendations:
    1. Infer interests from engagement history.
    2. Only consider topics in groups different from where the user engaged.
    3. Exclude topics the user has already interactively engaged with.
    4. Recommend only related matches; return nothing when none exist.
    """
    interest_history = aggregate_engagement_history(history or [])
    if not interest_history:
        return []

    interest_profile = build_interest_profile(interest_history)
    if not interest_profile["corpus"]:
        return []

    member_groups = {int(group_id) for group_id in (member_group_ids or [])}
    engaged_groups = {int(group_id) for group_id in (engaged_group_ids or [])}
    engaged_ids = collect_engaged_topic_ids(history or [], engaged_topic_ids)

    candidate_topics: List[Dict[str, Any]] = []
    candidate_texts: List[str] = []

    for topic in topics or []:
        topic_id = topic_id_from(topic)
        group_id = group_id_from(topic)
        if topic_id is None or group_id is None:
            continue
        if topic_id in engaged_ids:
            continue
        if group_id in engaged_groups:
            continue

        candidate_topics.append(topic)
        candidate_texts.append(build_topic_text(topic))

    if not candidate_topics:
        return []

    interest_texts = interest_profile["texts"]
    corpus = candidate_texts + interest_texts

    vectorizer = TfidfVectorizer(stop_words="english", ngram_range=(1, 2), min_df=1)
    matrix = vectorizer.fit_transform(corpus)
    candidate_matrix = matrix[: len(candidate_texts)]
    interest_matrix = matrix[len(candidate_texts) :]

    weights = np.array(
        [max(float(item.get("engagement_score", 0.2) or 0.2), 0.05) for item in interest_history],
        dtype=float,
    )
    weights = weights / weights.sum()

    interest_dense = interest_matrix.toarray() if hasattr(interest_matrix, "toarray") else np.asarray(interest_matrix)
    user_vector = np.average(interest_dense, axis=0, weights=weights).reshape(1, -1)
    profile_similarities = cosine_similarity(user_vector, candidate_matrix).ravel()

    related: List[Dict[str, Any]] = []

    for index, topic in enumerate(candidate_topics):
        topic_id = topic_id_from(topic)
        if topic_id is None:
            continue

        topic_text = candidate_texts[index]
        candidate_vector = candidate_matrix[index]

        engaged_similarities = [
            float(cosine_similarity(candidate_vector, interest_matrix[interest_index]).ravel()[0])
            for interest_index in range(interest_matrix.shape[0])
        ]
        profile_similarity = float(profile_similarities[index])
        best_engaged_similarity = max(engaged_similarities) if engaged_similarities else 0.0

        if not is_related_to_interests(
            topic_text,
            interest_profile,
            profile_similarity,
            engaged_similarities,
        ):
            continue

        best_pairwise_keywords = max(
            shared_keyword_count(topic_text, interest_text)
            for interest_text in interest_texts
        ) if interest_texts else 0
        lexical_score = min(best_pairwise_keywords / 3.0, 1.0)

        score = (
            best_engaged_similarity * 0.55
            + profile_similarity * 0.20
            + lexical_score * 0.25
        )

        group_id = group_id_from(topic)
        if group_id is not None and group_id not in member_groups:
            score += UNJOINED_GROUP_BOOST

        related.append(
            {
                "id": topic_id,
                "title": str(topic.get("title") or topic.get("Title") or ""),
                "description": str(
                    topic.get("description")
                    or topic.get("Description")
                    or topic.get("Topic_Description")
                    or ""
                ),
                "group_id": group_id_from(topic),
                "score": round(score, 3),
            }
        )

    related.sort(key=lambda item: item["score"], reverse=True)
    return related[:limit]


@app.get("/health")
def healthcheck() -> Dict[str, Any]:
    return {"status": "ok"}


@app.post("/recommend", response_model=RecommendationResponse)
def recommend(request: RecommendationRequest) -> RecommendationResponse:
    recommendations = recommend_topics(
        topics=request.topics or [],
        history=request.history or [],
        limit=request.limit,
        member_group_ids=request.member_group_ids,
        engaged_topic_ids=request.engaged_topic_ids,
        engaged_group_ids=request.engaged_group_ids,
    )
    return RecommendationResponse(user_id=request.user_id, recommendations=recommendations)


@app.get("/docs", include_in_schema=False)
async def custom_swagger_ui_html():
    return get_swagger_ui_html(
        openapi_url=app.openapi_url,
        title=app.title + " - Swagger UI",
        oauth2_redirect_url=app.swagger_ui_oauth2_redirect_url,
        swagger_js_url="https://cdnjs.cloudflare.com/ajax/libs/swagger-ui/4.15.5/swagger-ui-bundle.min.js",
        swagger_css_url="https://cdnjs.cloudflare.com/ajax/libs/swagger-ui/4.15.5/swagger-ui.min.css",
    )
