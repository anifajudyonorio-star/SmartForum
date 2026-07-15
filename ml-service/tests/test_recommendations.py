from app.main import recommend_topics


def test_recommend_topics_uses_history_and_returns_ranked_results():
    topics = [
        {"id": 1, "title": "API development", "description": "Build REST endpoints and authentication"},
        {"id": 2, "title": "Backend services", "description": "Design secure APIs and deploy authentication services"},
        {"id": 3, "title": "Frontend integration", "description": "Connect a UI to REST endpoints"},
    ]

    history = [{"topic_id": 1, "engagement_score": 0.95, "title": "API development"}]

    results = recommend_topics(topics, history, limit=2)

    assert len(results) == 2
    assert results[0]["id"] == 2
    assert results[0]["score"] >= 0
