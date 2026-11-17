"""Re-shoot the starter picker previews (resources/starters/previews/).

Needs a site with this package (php flo serve --port=8471 in /tmp/site21),
Playwright and Pillow. pictures.py draws the dummy pictures (SVG) into
/tmp/dummy; render them to JPEG first (1200x900), then run this.
"""
import subprocess, glob, base64, json, sys
from playwright.sync_api import sync_playwright
from PIL import Image
exe=sorted(glob.glob('/opt/pw-browsers/chromium*/chrome-linux*/chrome'))[0]
img={n:'data:image/jpeg;base64,'+base64.b64encode(open(f'/tmp/dummy/{n}.jpg','rb').read()).decode() for n in ['house','living','kitchen','bedroom','outdoor','lamp','table','vase','stone','daylight']}
plan={
 'studio':{'media':['house','living','kitchen','bedroom']},
 'slider':{'slides':['house','living','kitchen'],'media':['living','kitchen','bedroom']},
 'shop':{'media':['living','kitchen','bedroom','outdoor','lamp','table','stone','vase']},
 'journal':{'media':['daylight','stone','kitchen','living']},
 'landing':{'media':['house','living','kitchen']},
 'blank':{},
}
JS='''([media,slides])=>{
 const fill=(el,src)=>{el.style.backgroundImage=`url("${src}")`;el.style.backgroundSize='cover';el.style.backgroundPosition='center';el.querySelectorAll('svg,.fe-media__icon').forEach(x=>x.remove());};
 document.querySelectorAll('.fe-media--placeholder').forEach((el,i)=>{ if(media.length) fill(el,media[i%media.length]); });
 document.querySelectorAll('.fe-slider__image--empty').forEach((el,i)=>{ if(slides.length) fill(el,slides[i%slides.length]); });
}'''
only=sys.argv[1:] or list(plan)
with sync_playwright() as p:
    b=p.chromium.launch(executable_path=exe)
    for n in only:
        subprocess.run(['php','flo','site:starter',n,'--force','--no-interaction'],cwd='/tmp/site21',capture_output=True)
        for l in ['en','ar','ku']:
            pg=b.new_page(viewport={'width':1440,'height':1080})
            pg.goto(f'http://127.0.0.1:8471/{l}/'); pg.wait_for_load_state('networkidle')
            css='[class*="cookie"]{display:none!important} *{animation:none!important;transition:none!important}'
            if n=='blank': css+=' body{min-height:100vh;display:flex;flex-direction:column} main,#main{flex:1}'
            pg.add_style_tag(content=css)
            pg.evaluate(JS,[[img[x] for x in plan[n].get('media',[])],[img[x] for x in plan[n].get('slides',[])]])
            pg.wait_for_timeout(300); pg.screenshot(path=f'{n}-{l}.png'); pg.close()
            Image.open(f'{n}-{l}.png').convert('RGB').resize((720,540),Image.LANCZOS).save(f'/home/claude/flocms-frontend/resources/starters/previews/{n}-{l}-v1.webp',quality=82,method=6)
    b.close()
