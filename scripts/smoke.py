"""Explicit live API checks. Not run during normal builds or installation."""
import argparse
import json
import os
import urllib.request

time = {'StdTime': '10:30 15/05/1990 +08:00', 'Location': {'Name': 'Kuala Lumpur', 'Longitude': 101.6869, 'Latitude': 3.139}}
partner = dict(time, StdTime='14:00 20/07/1992 +08:00')
calls = {name: {'time': time} for name in ('AllPlanetRasiSigns', 'AllHouseBhavaChalitSigns', 'AllPlanetNavamshaSign')}
calls.update({
    'PanchangaTable': {'inputTime': time},
    'AllPlanetData': {'planetName': 'Sun', 'time': time},
    'AllHouseData': {'houseName': 'House1', 'time': time},
    'HoroscopePredictions': {'birthTime': time},
    'DasaAtTime': {'birthTime': time, 'at': dict(time, StdTime='12:00 11/11/2026 +08:00'), 'system': 'Vimshottari', 'depth': 3},
    'MatchReport': {'maleBirthTime': time, 'femaleBirthTime': partner},
})
def check(item):
    name, params = item
    params.update(Ayanamsa='RAMAN')
    request = urllib.request.Request('https://vedastro.zaishi.net/api/Calculate/' + name, data=json.dumps(params).encode(), headers={'Content-Type': 'application/json', 'x-api-key': os.environ.get('VEDASTRO_API_KEY', 'FreeAPIUser')})
    with urllib.request.urlopen(request, timeout=120) as response:
        data = json.load(response)
    if data.get('Status') != 'Pass' or 'Payload' not in data:
        raise RuntimeError(name + ' failed; inspect service contract/subscription')
    return name + ': PASS'
if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('methods', nargs='*', help='Optional subset; free tier allows five calls/minute.')
    args = parser.parse_args()
    selected = args.methods or list(calls)
    if any(method not in calls for method in selected):
        parser.error('Choose from: ' + ', '.join(calls))
    if len(selected) > 5 and not os.environ.get('VEDASTRO_API_KEY'):
        parser.error('Choose up to five methods per minute or set VEDASTRO_API_KEY to a subscription key.')
    for method in selected:
        print(check((method, calls[method])), flush=True)
