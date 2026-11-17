# Dummy pictures for the starter previews: simple, soft, photo-like scenes in SVG.
W,H=1200,900
def svg(body, defs=''):
    return f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {W} {H}" width="{W}" height="{H}">
<defs>
<filter id="soft"><feGaussianBlur stdDeviation="18"/></filter>
<filter id="blur6"><feGaussianBlur stdDeviation="6"/></filter>
<filter id="grain"><feTurbulence type="fractalNoise" baseFrequency="0.9" numOctaves="2" seed="3"/><feColorMatrix values="0 0 0 0 0.5  0 0 0 0 0.5  0 0 0 0 0.5  0 0 0 0.06 0"/><feComposite in2="SourceGraphic" operator="in"/></filter>
{defs}</defs>{body}<rect width="{W}" height="{H}" fill="#fff" filter="url(#grain)" opacity=".9"/></svg>'''

S={}
# 1 house at dusk
S['house']=svg('''
<rect width="1200" height="900" fill="url(#sky)"/>
<circle cx="930" cy="250" r="60" fill="#ffd9a8" opacity=".55" filter="url(#soft)"/>
<path d="M0 600 Q300 560 600 590 T1200 570 V900 H0Z" fill="#3c4a3f"/>
<g filter="url(#blur6)" opacity=".7"><ellipse cx="120" cy="560" rx="110" ry="140" fill="#2d3a30"/><ellipse cx="1090" cy="545" rx="130" ry="160" fill="#2a362d"/></g>
<rect x="260" y="330" width="700" height="300" fill="#e9e4dc"/>
<rect x="220" y="310" width="780" height="26" fill="#d8d1c6"/>
<rect x="520" y="200" width="380" height="140" fill="#efeae2"/>
<rect x="500" y="186" width="420" height="20" fill="#d8d1c6"/>
<rect x="560" y="230" width="300" height="90" fill="url(#glow)"/>
<rect x="300" y="380" width="200" height="250" fill="url(#glow)"/>
<rect x="540" y="380" width="380" height="180" fill="url(#glow)"/>
<g stroke="#5b5146" stroke-width="4" opacity=".6"><line x1="400" y1="380" x2="400" y2="630"/><line x1="730" y1="380" x2="730" y2="560"/><line x1="710" y1="230" x2="710" y2="320"/></g>
<rect x="560" y="560" width="400" height="70" fill="#d3ccc1"/>
<path d="M0 640 H1200 V900 H0Z" fill="#46553f"/>
<path d="M480 640 L720 640 L820 900 L380 900Z" fill="#cfc6b8"/>
<rect x="0" y="630" width="1200" height="14" fill="#2f3b2f" opacity=".5"/>
<g fill="#ffcf8a" opacity=".5" filter="url(#soft)"><rect x="300" y="600" width="200" height="60"/><rect x="540" y="560" width="380" height="60"/></g>
''','''<linearGradient id="sky" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#2b4a66"/><stop offset=".55" stop-color="#8aa3b5"/><stop offset="1" stop-color="#f0c9a4"/></linearGradient>
<linearGradient id="glow" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ffd79a"/><stop offset="1" stop-color="#f3a95c"/></linearGradient>''')
# 2 living room
S['living']=svg('''
<rect width="1200" height="900" fill="#e8e1d6"/>
<rect y="640" width="1200" height="260" fill="url(#floor)"/>
<rect x="120" y="120" width="380" height="440" fill="url(#win)"/>
<g stroke="#cfc6b8" stroke-width="10"><line x1="310" y1="120" x2="310" y2="560"/><line x1="120" y1="340" x2="500" y2="340"/></g>
<rect x="110" y="110" width="400" height="460" fill="none" stroke="#d6cdbf" stroke-width="16"/>
<path d="M140 640 L560 900 H1200 V700 L700 640Z" fill="#fff4dc" opacity=".35" filter="url(#soft)"/>
<ellipse cx="760" cy="700" rx="360" ry="30" fill="#000" opacity=".12" filter="url(#soft)"/>
<rect x="470" y="520" width="600" height="150" rx="26" fill="#8c9a8a"/>
<rect x="500" y="440" width="540" height="120" rx="30" fill="#9aa898"/>
<rect x="440" y="500" width="80" height="170" rx="28" fill="#7f8d7e"/>
<rect x="1020" y="500" width="80" height="170" rx="28" fill="#7f8d7e"/>
<rect x="600" y="470" width="140" height="100" rx="20" fill="#d9c2a0"/>
<rect x="770" y="480" width="130" height="95" rx="20" fill="#c6a98b"/>
<rect x="560" y="740" width="320" height="22" rx="8" fill="#6b5847"/>
<rect x="580" y="760" width="12" height="70" fill="#5a493a"/><rect x="848" y="760" width="12" height="70" fill="#5a493a"/>
<line x1="1120" y1="0" x2="1120" y2="250" stroke="#3a3a3a" stroke-width="3"/>
<path d="M1060 250 Q1120 200 1180 250Z" fill="#2d3640"/>
<ellipse cx="1120" cy="262" rx="70" ry="22" fill="#ffd79a" opacity=".6" filter="url(#soft)"/>
<rect x="40" y="560" width="70" height="90" rx="6" fill="#b8a48d"/>
<g fill="#5f7a55"><ellipse cx="75" cy="470" rx="40" ry="90"/><ellipse cx="40" cy="520" rx="30" ry="70" transform="rotate(-25 40 520)"/><ellipse cx="110" cy="515" rx="30" ry="75" transform="rotate(25 110 515)"/></g>
''','''<linearGradient id="floor" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#b99c7d"/><stop offset="1" stop-color="#9a7d61"/></linearGradient>
<linearGradient id="win" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#f6f1e6"/><stop offset="1" stop-color="#d9e3e6"/></linearGradient>''')
# 3 kitchen
S['kitchen']=svg('''
<rect width="1200" height="900" fill="#efeae3"/>
<rect y="0" width="1200" height="300" fill="#e2dbd1"/>
<rect x="60" y="60" width="1080" height="220" fill="#cbbfae"/>
<g fill="#d7ccbc"><rect x="70" y="70" width="255" height="200"/><rect x="335" y="70" width="255" height="200"/><rect x="610" y="70" width="255" height="200"/><rect x="875" y="70" width="255" height="200"/></g>
<rect x="60" y="300" width="1080" height="170" fill="#f6f3ee"/>
<rect x="60" y="470" width="1080" height="20" fill="#bfb3a3"/>
<rect x="60" y="490" width="1080" height="250" fill="#2f4152"/>
<g stroke="#26364a" stroke-width="4"><line x1="330" y1="490" x2="330" y2="740"/><line x1="600" y1="490" x2="600" y2="740"/><line x1="870" y1="490" x2="870" y2="740"/></g>
<rect y="740" width="1200" height="160" fill="#c9bba8"/>
<rect x="250" y="580" width="700" height="34" fill="#f1ede6"/>
<rect x="270" y="614" width="660" height="230" fill="#3a4e60"/>
<ellipse cx="600" cy="860" rx="380" ry="20" fill="#000" opacity=".18" filter="url(#soft)"/>
<g><line x1="440" y1="0" x2="440" y2="330" stroke="#333" stroke-width="3"/><line x1="760" y1="0" x2="760" y2="330" stroke="#333" stroke-width="3"/>
<path d="M400 370 L440 330 L480 370Z" fill="#b8925a"/><path d="M720 370 L760 330 L800 370Z" fill="#b8925a"/>
<ellipse cx="440" cy="380" rx="60" ry="22" fill="#ffd79a" opacity=".7" filter="url(#soft)"/><ellipse cx="760" cy="380" rx="60" ry="22" fill="#ffd79a" opacity=".7" filter="url(#soft)"/></g>
<rect x="380" y="545" width="60" height="38" rx="6" fill="#e9e1d4"/><circle cx="820" cy="560" r="24" fill="#d39a5b"/><circle cx="855" cy="565" r="20" fill="#e2b56f"/>
''')
# 4 bedroom
S['bedroom']=svg('''
<rect width="1200" height="900" fill="#dfe3e1"/>
<rect y="660" width="1200" height="240" fill="#b7a58f"/>
<rect x="250" y="250" width="700" height="300" rx="16" fill="#7d8c97"/>
<ellipse cx="600" cy="800" rx="460" ry="40" fill="#000" opacity=".14" filter="url(#soft)"/>
<rect x="200" y="520" width="800" height="230" rx="20" fill="#f4f0e8"/>
<path d="M200 600 H1000 V750 Q600 780 200 750Z" fill="#cdb79a"/>
<rect x="290" y="440" width="260" height="110" rx="30" fill="#fbf8f2"/>
<rect x="650" y="440" width="260" height="110" rx="30" fill="#fbf8f2"/>
<rect x="60" y="560" width="120" height="110" rx="6" fill="#8b6f53"/><rect x="1020" y="560" width="120" height="110" rx="6" fill="#8b6f53"/>
<rect x="105" y="470" width="30" height="90" fill="#cbb38d"/><path d="M70 470 H170 L150 400 H90Z" fill="#efe6d5"/>
<ellipse cx="120" cy="440" rx="90" ry="70" fill="#ffd79a" opacity=".4" filter="url(#soft)"/>
<rect x="420" y="90" width="360" height="120" fill="#c5b49d" opacity=".7"/>
''')
# 5 outdoor
S['outdoor']=svg('''
<rect width="1200" height="900" fill="url(#sky2)"/>
<g filter="url(#blur6)" opacity=".8"><ellipse cx="200" cy="460" rx="230" ry="190" fill="#6f8a5f"/><ellipse cx="1000" cy="430" rx="260" ry="220" fill="#5f7a52"/></g>
<rect y="560" width="1200" height="340" fill="#d8c7ae"/>
<g stroke="#e9e1d6" stroke-width="3" opacity=".7">''' + ''.join(f'<line x1="{x}" y1="560" x2="{x*1.6-360}" y2="900"/>' for x in range(0,1300,120)) + '''</g>
<g fill="#4b3a2c"><rect x="220" y="230" width="22" height="420"/><rect x="960" y="230" width="22" height="420"/><rect x="190" y="220" width="820" height="26"/></g>
<g fill="#4b3a2c" opacity=".85">''' + ''.join(f'<rect x="{x}" y="200" width="12" height="60"/>' for x in range(220,1000,48)) + '''</g>
<ellipse cx="600" cy="760" rx="300" ry="30" fill="#000" opacity=".15" filter="url(#soft)"/>
<rect x="430" y="640" width="340" height="110" rx="14" fill="#ece7df"/>
<rect x="450" y="600" width="300" height="60" rx="18" fill="#f6f3ee"/>
<circle cx="880" cy="700" r="50" fill="#a7744a"/><rect x="860" y="700" width="40" height="70" fill="#8a5f3c"/>
''','<linearGradient id="sky2" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#9cc0d6"/><stop offset="1" stop-color="#eef1ea"/></linearGradient>')
# 6 lamp product
S['lamp']=svg('''
<rect width="1200" height="900" fill="#ece6dd"/>
<ellipse cx="600" cy="620" rx="320" ry="160" fill="#ffd79a" opacity=".45" filter="url(#soft)"/>
<line x1="600" y1="0" x2="600" y2="340" stroke="#2b2b2b" stroke-width="4"/>
<path d="M430 520 Q430 340 600 340 Q770 340 770 520Z" fill="#f4efe6"/>
<path d="M430 520 Q430 340 600 340 Q770 340 770 520Z" fill="url(#shade)"/>
<ellipse cx="600" cy="520" rx="170" ry="26" fill="#ffe2ad"/>
<ellipse cx="600" cy="840" rx="260" ry="20" fill="#000" opacity=".08" filter="url(#soft)"/>
''','<linearGradient id="shade" x1="0" x2="1"><stop offset="0" stop-color="#000" stop-opacity=".12"/><stop offset=".5" stop-color="#000" stop-opacity="0"/><stop offset="1" stop-color="#000" stop-opacity=".18"/></linearGradient>')
# 7 side table
S['table']=svg('''
<rect width="1200" height="900" fill="#e4ddd2"/>
<rect y="640" width="1200" height="260" fill="#d3c8b8"/>
<ellipse cx="600" cy="720" rx="300" ry="34" fill="#000" opacity=".16" filter="url(#soft)"/>
<ellipse cx="600" cy="360" rx="260" ry="54" fill="#8a6446"/><rect x="340" y="360" width="520" height="30" fill="#7a573c"/><ellipse cx="600" cy="390" rx="260" ry="54" fill="#7a573c"/><ellipse cx="600" cy="360" rx="260" ry="54" fill="#9a7252"/>
<path d="M560 420 L520 710 H560 L600 430Z M640 420 L680 710 H640 L600 430Z" fill="#6a4a32"/>
<rect x="520" y="250" width="70" height="110" rx="10" fill="#e9e2d6"/><circle cx="690" cy="320" r="38" fill="#b3c0b0"/>
''')
# 8 rug & vase
S['vase']=svg('''
<rect width="1200" height="900" fill="#efe9e0"/>
<ellipse cx="600" cy="760" rx="230" ry="26" fill="#000" opacity=".14" filter="url(#soft)"/>
<path d="M500 760 Q470 560 540 470 Q560 420 540 360 H660 Q640 420 660 470 Q730 560 700 760Z" fill="#c9b49a"/>
<path d="M500 760 Q470 560 540 470 Q560 420 540 360 H660 Q640 420 660 470 Q730 560 700 760Z" fill="url(#shade2)"/>
<g stroke="#6d7d5f" stroke-width="4" fill="none"><path d="M590 360 Q560 200 470 120"/><path d="M610 360 Q640 220 740 150"/><path d="M600 360 Q600 200 610 90"/></g>
<g fill="#8c9c77"><ellipse cx="470" cy="120" rx="30" ry="14"/><ellipse cx="740" cy="150" rx="30" ry="14"/><ellipse cx="610" cy="90" rx="14" ry="30"/><ellipse cx="520" cy="190" rx="26" ry="12"/><ellipse cx="690" cy="230" rx="26" ry="12"/></g>
''','<linearGradient id="shade2" x1="0" x2="1"><stop offset="0" stop-color="#000" stop-opacity=".18"/><stop offset=".45" stop-color="#fff" stop-opacity=".12"/><stop offset="1" stop-color="#000" stop-opacity=".22"/></linearGradient>')
# 9 stone / materials
S['stone']=svg('''
<rect width="1200" height="900" fill="#d9d3c9"/>
''' + ''.join(f'<rect x="{(c*300+(r%2)*150)-150}" y="{r*180}" width="292" height="172" rx="6" fill="{["#cfc7ba","#d8d0c4","#c7beb0","#ddd6cb"][(r+c)%4]}"/>' for r in range(5) for c in range(6)) + '''
<path d="M0 0 L1200 0 L1200 900 L700 900Z" fill="#fff6e2" opacity=".25"/>
<ellipse cx="300" cy="260" rx="400" ry="260" fill="#fff3d6" opacity=".35" filter="url(#soft)"/>
''')
# 10 daylight window
S['daylight']=svg('''
<rect width="1200" height="900" fill="#e9e4dc"/>
<rect x="380" y="90" width="440" height="560" fill="#f8f5ee"/>
<rect x="380" y="90" width="440" height="560" fill="none" stroke="#cfc6b8" stroke-width="18"/>
<line x1="600" y1="90" x2="600" y2="650" stroke="#cfc6b8" stroke-width="12"/>
<path d="M380 650 L820 650 L1100 900 L180 900Z" fill="#fff7e4" opacity=".7"/>
<rect y="650" width="1200" height="250" fill="#c2ab90" opacity=".85"/>
<path d="M380 650 L820 650 L1100 900 L180 900Z" fill="#fff4d8" opacity=".45"/>
<rect x="860" y="500" width="160" height="160" rx="8" fill="#9b7b5c"/><g fill="#6f8b60"><ellipse cx="940" cy="430" rx="60" ry="90"/></g>
''')
for k,v in S.items(): open(f'{k}.svg','w').write(v)
print(list(S))
