#!/usr/bin/env python3
"""Clean the food-industry company spreadsheet into cas_career_companies.json.

Usage: python3 scripts-dev/cas_career_companies_build.py "<csv path>"
The JSON is what cas_career_import.php reads (it ships with the code so the
import can run on Acquia without the CSV).
"""
import csv
import io
import json
import os
import sys

STATES = {
    'AL': 'Alabama', 'AK': 'Alaska', 'AZ': 'Arizona', 'AR': 'Arkansas', 'CA': 'California',
    'CO': 'Colorado', 'CT': 'Connecticut', 'DE': 'Delaware', 'FL': 'Florida', 'GA': 'Georgia',
    'HI': 'Hawaii', 'ID': 'Idaho', 'IL': 'Illinois', 'IN': 'Indiana', 'IA': 'Iowa',
    'KS': 'Kansas', 'KY': 'Kentucky', 'LA': 'Louisiana', 'ME': 'Maine', 'MD': 'Maryland',
    'MA': 'Massachusetts', 'MI': 'Michigan', 'MN': 'Minnesota', 'MS': 'Mississippi',
    'MO': 'Missouri', 'MT': 'Montana', 'NE': 'Nebraska', 'NV': 'Nevada', 'NH': 'New Hampshire',
    'NJ': 'New Jersey', 'NM': 'New Mexico', 'NY': 'New York', 'NC': 'North Carolina',
    'ND': 'North Dakota', 'OH': 'Ohio', 'OK': 'Oklahoma', 'OR': 'Oregon', 'PA': 'Pennsylvania',
    'RI': 'Rhode Island', 'SC': 'South Carolina', 'SD': 'South Dakota', 'TN': 'Tennessee',
    'TX': 'Texas', 'UT': 'Utah', 'VT': 'Vermont', 'VA': 'Virginia', 'WA': 'Washington',
    'WV': 'West Virginia', 'WI': 'Wisconsin', 'WY': 'Wyoming',
}
# CSV label -> existing cas_career_industry term name.
INDUSTRY = {'Beverage': 'Beverages (nonalcoholic)', 'MicroBio': 'Microbiology'}
TITLE_FIX = {'Nestle‚ USA': 'Nestlé USA'}
CITY_FIX = {'Millwaukie': 'Milwaukie', 'Chicago, Illinois': 'Chicago',
            'Springdale, Arkansas': 'Springdale'}


def url(v):
    v = v.strip()
    if v.upper().startswith('OR '):  # "OR https://..." typo
        v = v[3:].strip()
    if v and not v.lower().startswith('http'):
        v = 'https://' + v
    return v


def main(path):
    text = open(path, 'rb').read().decode('cp1252')
    rows = list(csv.reader(io.StringIO(text)))[1:]
    out = {}
    for r in rows:
        if not any(c.strip() for c in r):
            continue
        title, industry, city, state, region, about, careers = (c.strip() for c in r)
        title = TITLE_FIX.get(title, title)
        city = CITY_FIX.get(city, city)
        industries = [INDUSTRY.get(i.strip(), i.strip()) for i in industry.split(',') if i.strip()]
        rec = {
            'title': title,
            'industry': industries,
            'city': city,
            'state': STATES[state.upper()],
            'region': region,
            'about': url(about),
            'careers': url(careers),
        }
        key = title.lower()
        if key in out:
            prev = out[key]
            # Merge duplicates: keep the more complete row, union industries.
            if len([v for v in rec.values() if v]) > len([v for v in prev.values() if v]) or (rec['city'] and not prev['city']):
                rec['industry'] = prev['industry'] + [i for i in rec['industry'] if i not in prev['industry']]
                out[key] = rec
            else:
                prev['industry'] += [i for i in rec['industry'] if i not in prev['industry']]
            print('merged duplicate:', title, file=sys.stderr)
            continue
        out[key] = rec
    data = sorted(out.values(), key=lambda d: d['title'].lower())
    dest = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'cas_career_companies.json')
    with open(dest, 'w') as f:
        json.dump(data, f, indent=1, ensure_ascii=False)
        f.write('\n')
    print(f'{len(data)} companies -> {dest}', file=sys.stderr)


if __name__ == '__main__':
    main(sys.argv[1])
