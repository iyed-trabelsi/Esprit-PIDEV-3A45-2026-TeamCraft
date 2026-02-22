import pandas as pd
from sklearn.model_selection import train_test_split
from sklearn.ensemble import RandomForestClassifier
from sklearn.metrics import classification_report
import joblib

# Load dataset
data = pd.read_csv("matching_dataset.csv")

# Features
X = data.drop("accepted", axis=1)

# Target
y = data["accepted"]

# Split data
X_train, X_test, y_train, y_test = train_test_split(
    X, y, test_size=0.2, random_state=42
)

# Create model
model = RandomForestClassifier(
    n_estimators=100,
    max_depth=10,
    random_state=42
)

# Train model
model.fit(X_train, y_train)

# Evaluate
predictions = model.predict(X_test)
print(classification_report(y_test, predictions))

# Save model
joblib.dump(model, "matching_model.pkl")

print("Model trained and saved successfully!")
