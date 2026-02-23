from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
import joblib
import os
import numpy as np

app = FastAPI(title="TeamCraft AI Matching Service")

# Model path
MODEL_PATH = "matching_model.pkl"
model = None

if os.path.exists(MODEL_PATH):
    try:
        model = joblib.load(MODEL_PATH)
        print("Model loaded successfully.")
    except Exception as e:
        print(f"Failed to load model: {e}")

class PredictRequest(BaseModel):
    player_rank: int
    player_kd: float
    player_winrate: float
    player_role: str
    offer_required_rank: int
    offer_role: str

def compute_compatibility_level(score: float) -> str:
    if score >= 80:
        return "High"
    elif score >= 50:
        return "Medium"
    else:
        return "Low"

def scoring_logic_fallback(data: PredictRequest) -> float:
    # 1. Rank similarity (40%)
    # Assuming max rank diff is around 12 (Iron to Radiant)
    rank_diff = abs(data.player_rank - data.offer_required_rank)
    rank_score = max(0, 40 * (1 - (rank_diff / 13)))

    # 2. Role match (30%)
    role_score = 30 if data.player_role.lower() == data.offer_role.lower() else 0
    # Add partial credit for fuzzy match if needed?
    if role_score == 0 and (data.player_role.lower() in data.offer_role.lower() or data.offer_role.lower() in data.player_role.lower()):
        role_score = 15

    # 3. K/D comparison (20%)
    # Base on 1.0 as average, 2.0 as excellent
    kd_score = min(20, (data.player_kd / 2.0) * 20)

    # 4. Winrate comparison (10%)
    winrate_score = (data.player_winrate / 100.0) * 10

    total_score = rank_score + role_score + kd_score + winrate_score
    return round(min(100.0, total_score), 2)

class BatchPredictRequest(BaseModel):
    player_rank: int
    player_kd: float
    player_winrate: float
    player_role: str
    offers: list[dict] # [{id: int, rank: int, role: str}]

@app.post("/predict_batch")
async def predict_batch(request: BatchPredictRequest):
    try:
        results = {}
        for offer in request.offers:
            # Create a PredictRequest-like object for the internal scoring logic
            # This is efficient since it's all in-process now
            temp_req = PredictRequest(
                player_rank=request.player_rank,
                player_kd=request.player_kd,
                player_winrate=request.player_winrate,
                player_role=request.player_role,
                offer_required_rank=offer["rank"],
                offer_role=offer["role"]
            )
            score = scoring_logic_fallback(temp_req)
            results[str(offer["id"])] = {
                "match_score": score,
                "compatibility_level": compute_compatibility_level(score)
            }
        
        return results
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/predict")
async def predict(request: PredictRequest):
    try:
        # If we have a model, we could use it here. 
        # However, the user provided a specific weighted formula requirement.
        # We will prioritize the formula as requested, or combine them.
        
        # Here we follow the "If ML model does not exist" instruction as a primary logic 
        # or as the requested implementation if the model doesn't match the new feature set.
        
        # For this project, we'll use the scoring logic formula to ensure it matches the user's specific weights.
        score = scoring_logic_fallback(request)
        
        return {
            "match_score": score,
            "compatibility_level": compute_compatibility_level(score)
        }
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8000)
