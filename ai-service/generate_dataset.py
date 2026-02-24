import pandas as pd
import random

data = []

for i in range(1000):

    player_rank = random.randint(1, 9)
    offer_required_rank = random.randint(1, 9)

    role_match = random.choice([0, 1])
    region_match = random.choice([0, 1])

    experience_diff = random.randint(-3, 3)

    # Smart logic for acceptance
    if (
        player_rank >= offer_required_rank
        and role_match == 1
        and region_match == 1
    ):
        accepted = 1
    else:
        accepted = 0

    data.append([
        player_rank,
        offer_required_rank,
        role_match,
        experience_diff,
        region_match,
        accepted
    ])

columns = [
    "player_rank",
    "offer_required_rank",
    "role_match",
    "experience_diff",
,
    "accepted"
]

df = pd.DataFrame(data, columns=columns)

df.to_csv("matching_dataset.csv", index=False)

print("Dataset generated successfully!")
