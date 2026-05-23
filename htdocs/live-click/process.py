#!/usr/bin/python3

# Used to process the information from the drive sheet. Download as cvs and remove the header
# Copy the output to the data.js file

import json

fs = open("songs.csv", "r")

songs = []
i = 0

line = fs.readline()
while line:
    record = line.split(",")
    r = {
        "id": i,
        "title": record[2],
        "artist": record[1],
        "bpm": record[5],
        "starts": record[7],
        "description": record[0],
    }
    songs.append(r)
    i = i + 1
    line = fs.readline()

fs.close() 

print(json.dumps(songs, indent=4))
