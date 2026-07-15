# SmartForum Recommendation Service

This FastAPI service generates topic recommendations for SmartForum users based on their engagement history and the available topics.

## local setup
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
uvicorn app.main:app --host 0.0.0.0 --port 5000
```

## Endpoints

- GET /health
- POST /recommend
