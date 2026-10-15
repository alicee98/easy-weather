"""Run against PHP dev server: EW_TEST_URL=http://127.0.0.1:8085/ python3 tests/http_test.py"""
import json, os, urllib.request, urllib.error, http.cookiejar
BASE=os.getenv('EW_TEST_URL','http://127.0.0.1:8080/').rstrip('/')+'/'
jar=http.cookiejar.CookieJar();client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
def request(path,body=None,csrf=None):
 headers={}
 if body is not None:headers['Content-Type']='application/json'
 if csrf:headers['X-CSRF-Token']=csrf
 req=urllib.request.Request(BASE+path,data=json.dumps(body).encode() if body is not None else None,headers=headers)
 try:
  with client.open(req,timeout=25) as r:return r.status,r.headers,r.read()
 except urllib.error.HTTPError as r:return r.code,r.headers,r.read()
status,headers,html=request('index.php');assert status==200 and b'app-config' in html
assert 'HttpOnly' in headers.get('Set-Cookie','')
_,_,body=request('api/index.php?action=session');session=json.loads(body);assert session['authenticated'] is False
assert request('api/index.php?action=search&name=Bandung')[0]==401
assert request('api/index.php?action=weather&latitude=-6.9175&longitude=107.6191')[0]==401
assert request('api/index.php?action=login',{'username':'alfi1','password':'easyweather'})[0]==403
assert request('api/index.php?action=login',{'username':'alfi1','password':'wrong'},session['csrf'])[0]==401
status,_,body=request('api/index.php?action=login',{'username':'alfi1','password':'easyweather'},session['csrf']);assert status==200
logged=json.loads(body);assert logged['authenticated'] is True and logged['name']=='Alfi' and logged['csrf']!=session['csrf']
assert request('api/index.php?action=logout',{},session['csrf'])[0]==403
assert request('api/index.php?action=weather&latitude=abc&longitude=1')[0]==400
assert request('api/index.php?action=login')[0]==405
status,_,body=request('api/index.php?action=logout',{},logged['csrf']);assert status==200 and json.loads(body)['authenticated'] is False
assert request('api/index.php?action=search&name=Bandung')[0]==401
assert request('view.php')[0]==404
print('PASS: PHP page, HttpOnly cookie, CSRF, login, logout, authorization, invalid coordinates and HTTP methods')
