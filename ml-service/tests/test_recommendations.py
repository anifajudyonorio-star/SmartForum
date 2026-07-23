from app.main import recommend_topics


def test_recommend_topics_only_returns_interest_related_topics():
    topics = [
        {"id": 1, "title": "API development", "description": "Build REST endpoints", "group_id": 10},
        {"id": 2, "title": "Backend services", "description": "Design secure APIs and authentication", "group_id": 20},
        {"id": 3, "title": "Frontend integration", "description": "Connect a UI to REST endpoints", "group_id": 20},
        {"id": 4, "title": "Cooking basics", "description": "Learn kitchen safety and recipes", "group_id": 30},
    ]

    history = [
        {
            "topic_id": 1,
            "engagement_score": 0.95,
            "engagement_type": "post",
            "title": "API development",
            "description": "Build REST endpoints",
        }
    ]

    results = recommend_topics(
        topics,
        history,
        limit=5,
        member_group_ids=[10],
        engaged_topic_ids=[1],
        engaged_group_ids=[10],
    )

    result_ids = {result["id"] for result in results}

    assert result_ids.issubset({2, 3})
    assert 4 not in result_ids
    assert 1 not in result_ids


def test_recommend_topics_agile_waterfall_cross_group():
    history = [
        {
            "topic_id": 1,
            "engagement_score": 0.35,
            "engagement_type": "view",
            "title": "Agile and Waterfall Debate",
            "description": "Discussion comparing agile and waterfall methodologies",
        },
        {
            "topic_id": 2,
            "engagement_score": 1.0,
            "engagement_type": "created_topic",
            "title": "Agile and Waterfall",
            "description": "Overview of agile and waterfall project management",
        },
    ]

    topics = [
        {"id": 1, "title": "Agile and Waterfall Debate", "description": "Discussion comparing agile and waterfall methodologies", "group_id": 10},
        {"id": 2, "title": "Agile and Waterfall", "description": "Overview of agile and waterfall project management", "group_id": 20},
    ]

    results = recommend_topics(
        topics,
        history,
        limit=5,
        member_group_ids=[10, 20],
        engaged_topic_ids=[1],
        engaged_group_ids=[10],
    )

    assert len(results) == 1
    assert results[0]["id"] == 2
    assert results[0]["group_id"] == 20


def test_recommend_topics_skips_related_topic_in_same_group():
    topics = [
        {"id": 1, "title": "Agile and Waterfall Debate", "description": "Compare agile and waterfall", "group_id": 10},
        {"id": 2, "title": "Agile and Waterfall", "description": "Overview of agile and waterfall", "group_id": 10},
        {"id": 3, "title": "Agile and Waterfall", "description": "Overview of agile and waterfall", "group_id": 20},
    ]

    history = [
        {
            "topic_id": 1,
            "engagement_score": 0.85,
            "engagement_type": "post",
            "title": "Agile and Waterfall Debate",
            "description": "Compare agile and waterfall",
        }
    ]

    results = recommend_topics(
        topics,
        history,
        limit=5,
        engaged_topic_ids=[1],
        engaged_group_ids=[10],
    )

    assert len(results) == 1
    assert results[0]["id"] == 3
    assert results[0]["group_id"] == 20


def test_recommend_topics_can_surface_related_topic_in_other_group_even_if_member():
    topics = [
        {"id": 1, "title": "Java Programming", "description": "Learn java basics", "group_id": 10},
        {"id": 2, "title": "Java OOP Concepts", "description": "Object oriented programming in java", "group_id": 20},
    ]

    history = [
        {
            "topic_id": 1,
            "engagement_score": 0.85,
            "engagement_type": "view",
            "title": "Java Programming",
            "description": "Learn java basics",
        }
    ]

    results = recommend_topics(
        topics,
        history,
        limit=5,
        member_group_ids=[10, 20],
        engaged_topic_ids=[1],
        engaged_group_ids=[10],
    )

    assert len(results) == 1
    assert results[0]["id"] == 2


def test_recommend_topics_returns_empty_when_no_related_topics_exist():
    topics = [
        {"id": 4, "title": "Cooking basics", "description": "Learn kitchen safety and recipes", "group_id": 30},
        {"id": 5, "title": "Gardening tips", "description": "Grow vegetables at home", "group_id": 31},
    ]

    history = [
        {
            "topic_id": 1,
            "engagement_score": 0.95,
            "engagement_type": "post",
            "title": "API development",
            "description": "Build REST endpoints and authentication",
        }
    ]

    results = recommend_topics(
        topics,
        history,
        limit=5,
        member_group_ids=[10],
        engaged_topic_ids=[1],
        engaged_group_ids=[10],
    )

    assert results == []


def test_recommend_topics_excludes_engaged_topics():
    topics = [
        {"id": 5, "title": "Machine learning", "description": "Neural networks", "group_id": 40},
        {"id": 6, "title": "Deep learning", "description": "Advanced neural networks", "group_id": 41},
    ]

    history = [
        {
            "topic_id": 5,
            "engagement_score": 0.85,
            "engagement_type": "post",
            "title": "Machine learning",
            "description": "Neural networks",
        }
    ]

    results = recommend_topics(
        topics,
        history,
        limit=3,
        member_group_ids=[99],
        engaged_topic_ids=[5],
        engaged_group_ids=[40],
    )

    assert len(results) == 1
    assert results[0]["id"] == 6


def test_recommend_topics_returns_empty_without_engagement_history():
    topics = [
        {"id": 7, "title": "Topic A", "description": "Desc A", "group_id": 50},
    ]

    results = recommend_topics(topics, [], limit=3, member_group_ids=[10])

    assert results == []
