from fastapi import FastAPI
import joblib
import numpy as np

app = FastAPI()

model = joblib.load("matching_model.pkl")

@app.post("/predict")
def predict_match(features: dict):

    input_data = np.array([[
        features["player_rank"],
        features["offer_required_rank"],
        features["role_match"],
        features["experience_diff"],
        features["region_match"]
    ]])

    probability = model.predict_proba(input_data)[0][1]

    return {
        "match_score": round(probability * 100, 2)
    }
