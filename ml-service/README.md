# SmartForum Recommendation Service

This FastAPI service generates **discovery recommendations** for SmartForum users — similar to YouTube's "recommended for you" feed.

## How it works

1. **Build user interests** from engagement history:
   - Created topic (1.0)
   - Post (0.85)
   - Reply (0.65)
   - View (0.35)
2. **Aggregate** repeated interactions per topic into a weighted interest profile (TF-IDF + cosine similarity).
3. **Recommend only** topics that:
   - Are in groups the user is **not** a member of
   - Have **not** already been engaged with (viewed, posted, replied, or created)
   - Are **genuinely related** to the inferred interests (semantic similarity, direct match to an engaged topic, or meaningful shared keywords)
4. **Return no recommendations** when the user's interests do not match any available topics in other groups

## Local setup
1.**prerequisites** make sure you have python 3.10+ installed
2.**database** run the latest migrations to generate the AI tables
3.**navigate to this folder:**
```bash
cd ml-service

## create virtual environmemt
python -m venv .venv

##activate the virtual environment
.venv\Scripts\Activate.ps1


## Run locally

```bash
pip install -r requirements.txt
uvicorn app.main:app --host 127.0.0.1 --port 5001
```

On Windows, port 5000 is often blocked by the system. Use **5001** and set this in your Laravel `.env`:

```
ML_SERVICE_URL=http://localhost:5001
```

Or run the helper script from the project root:

```powershell
.\ml-service\start-ml.ps1
```

## Endpoints

- GET /health
- POST /recommend

### POST /recommend payload

```json
{
  "user_id": 1,
  "member_group_ids": [10, 11],
  "engaged_topic_ids": [5, 6],
  "history": [
    {
      "topic_id": 5,
      "engagement_type": "post",
      "engagement_score": 0.85,
      "title": "API development",
      "description": "Build REST endpoints"
    }
  ],
  "topics": [
    {
      "id": 20,
      "title": "Backend services",
      "description": "Design secure APIs",
      "group_id": 99,
      "group_name": "Advanced Backend"
    }
  ],
  "limit": 5
}
```
