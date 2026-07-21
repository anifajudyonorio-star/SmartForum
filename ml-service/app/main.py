from functools import lru_cache
from pathlib import Path
import re
from typing import Any, Dict, List

import numpy as np
import pandas as pd
from fastapi import FastAPI
from pydantic import BaseModel
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity
from fastapi.openapi.docs import get_swagger_ui_html


app= FastAPI(docs_url=None, redoc_url=None, title="SmartForum Recommendation Service", version="1.0.0")


DATASET_PATH = Path(__file__).resolve().parents[1] / "data" / "student_learning_interaction_dataset.csv"


class RecommendationRequest(BaseModel):
    user_id: int
    topics: List[Dict[str, Any]] | None = None
    history: List[Dict[str, Any]] | None = None
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
    return str(value).strip().lower()


def build_topic_text(topic: Dict[str, Any]) -> str:
    title = normalize_text(topic.get("title") or topic.get("Title") or topic.get("topic_title"))
    description = normalize_text(topic.get("description") or topic.get("Description") or topic.get("Topic_Description"))
    return f"{title} {description}".strip()


def build_history_text(item: Dict[str, Any]) -> str:
    title = normalize_text(item.get("title") or item.get("topic_title") or item.get("description"))
    description = normalize_text(item.get("description") or item.get("topic_description"))
    engagement = str(item.get("engagement_score", 0) or 0)
    return f"{title} {description} engagement {engagement}".strip()


def build_dataset_context() -> List[str]:
    df = load_interaction_dataset()
    if df.empty:
        return []

    contexts: List[str] = []
    for module_id, group in df.groupby("module_id", dropna=False):
        if pd.isna(module_id):
            continue
        success_rate = float(group["success_label"].mean()) if "success_label" in group.columns else 0.0
        time_spent = float(group["time_spent_minutes"].mean()) if "time_spent_minutes" in group.columns else 0.0
        feedback_types = " ".join(str(value) for value in group.get("feedback_type", []).fillna(""))
        contexts.append(
            f"module {module_id} success {success_rate:.2f} time {time_spent:.1f} feedback {feedback_types}".strip()
        )
    return contexts


def token_overlap_score(left: str, right: str) -> float:
    left_tokens = set(re.findall(r"[a-z0-9]{2,}", left.lower()))
    right_tokens = set(re.findall(r"[a-z0-9]{2,}", right.lower()))
    if not right_tokens:
        return 0.0
    return len(left_tokens & right_tokens) / len(right_tokens)


def recommend_topics(topics: List[Dict[str, Any]], history: List[Dict[str, Any]], limit: int = 5) -> List[Dict[str, Any]]:
    if not topics:
        return []

    engaged_ids = set()
    for item in history or []:
        raw_id = item.get("topic_id") or item.get("id") or item.get("Topic_ID")
        if raw_id is not None:
            engaged_ids.add(int(raw_id))
    topic_texts = [build_topic_text(topic) for topic in topics]
    history_texts = [build_history_text(item) for item in history or []]
    dataset_context = build_dataset_context()
    corpus = topic_texts + history_texts + dataset_context

    if not corpus:
        return []

    vectorizer = TfidfVectorizer(stop_words="english", ngram_range=(1, 2), min_df=1)
    matrix = vectorizer.fit_transform(corpus)
    topic_matrix = matrix[: len(topic_texts)]
    history_matrix = matrix[len(topic_texts) : len(topic_texts) + len(history_texts)]

    if history_matrix.shape[0] > 0:
        user_vector = history_matrix.mean(axis=0)
    else:
        user_vector = topic_matrix[0] if topic_matrix.shape[0] > 0 else matrix[0]

    if hasattr(user_vector, "toarray"):
        user_vector = user_vector.toarray()
    user_vector = np.asarray(user_vector).reshape(1, -1)

    similarities = cosine_similarity(user_vector, topic_matrix).ravel()

    scored: List[Dict[str, Any]] = []
    engagement_counts ={}
    for item in history:
        h_id = int(item.get("topic_id")or 0)
        engagement_counts[h_id] = engagement_counts.get(h_id, 0) + 1
    for index, topic in enumerate(topics):
        topic_id = int(topic.get("id") or topic.get("topic_id") or topic.get("Topic_ID"))
        #if topic_id in engaged_ids:
        #    continue

        engagement_frequency  = engagement_counts.get(topic_id, 0)

        topic_text = topic_texts[index]
        title = str(topic.get("title") or topic.get("Title") or "")
        description = str(topic.get("description") or topic.get("Description") or topic.get("Topic_Description") or "")

        history_text = " ".join(history_texts) if history_texts else ""
        lexical = max(token_overlap_score(topic_text, history_text), token_overlap_score(title, history_text))
        engagement_bonus = sum(float(item.get("engagement_score", 0) or 0) for item in history if int(item.get("topic_id") or 0) == topic_id) 

        similarity_score = float(similarities[index]) 

        frequency_boost = engagement_frequency * 0.5

        score = (similarity_score * 0.4) + (lexical * 0.1) + engagement_bonus

        scored.append({
            "id": topic_id,
            "title": title,
            "description": description,
            "score": round(score, 3),
        })

        topic_text = topic_texts[index]
        title = str(topic.get("title") or topic.get("Title") or "")
        description = str(topic.get("description") or topic.get("Description") or topic.get("Topic_Description") or "")
        history_text = " ".join(history_texts) if history_texts else ""
        lexical = max(token_overlap_score(topic_text, history_text), token_overlap_score(title, history_text))
        engagement_bonus = sum(float(item.get("engagement_score", 0) or 0) for item in history or []) * 0.02
        score = float(similarities[index]) * 0.8 + lexical * 0.2 + engagement_bonus

        scored.append({
            "id": topic_id,
            "title": title,
            "description": description,
            "score": round(score, 3),
        })

    scored.sort(key=lambda item: item["score"], reverse=True)
    return scored[:limit]


@app.get("/health")
def healthcheck() -> Dict[str, Any]:
    return {"status": "ok"}


@app.post("/recommend", response_model=RecommendationResponse)
def recommend(request: RecommendationRequest) -> RecommendationResponse:
    recommendations = recommend_topics(
        topics=request.topics or [],
        history=request.history or [],
        limit=request.limit,
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