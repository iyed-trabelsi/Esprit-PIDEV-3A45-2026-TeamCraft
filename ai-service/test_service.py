import urllib.request
import json

url = "http://127.0.0.1:8000/predict"
payload = {
    "player_rank": 7,
    "player_kd": 1.5,
    "player_winrate": 60.0,
    "player_role": "Duelist",
    "offer_required_rank": 7,
    "offer_role": "Duelist"
}

data = json.dumps(payload).encode('utf-8')
req = urllib.request.Request(url, data=data)
req.add_header('Content-Type', 'application/json')

try:
    with urllib.request.urlopen(req) as response:
        status = response.getcode()
        body = response.read().decode('utf-8')
        print(f"Status Code: {status}")
        print(f"Response Body: {json.dumps(json.loads(body), indent=4)}")
except Exception as e:
    print(f"Error: {e}")
