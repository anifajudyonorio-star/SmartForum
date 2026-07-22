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
