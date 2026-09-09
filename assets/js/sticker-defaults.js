/* sticker-defaults.js — production sticker overlay
   Injects default sticker decorations into .inicio-stage
   without requiring the composer. */
(function(){
'use strict';

var DESKTOP=[
  {id:'ds1',k:'sparkle',x:1436,y:100,w:44,r:0},
  {id:'ds2',k:'rainbow',x:1518,y:765,w:114,r:0},
  {id:'ds3',k:'house',x:1207,y:52,w:67,r:0},
  {id:'ds4',k:'bird',x:1692,y:-6,w:44,r:0},
  {id:'ds5',k:'star-malva',x:465,y:1,w:28,r:0},
  {id:'ds6',k:'star-azul',x:452,y:33,w:21,r:0},
  {id:'ds7',k:'balloon',x:1567,y:750,w:29,r:-20},
  {id:'ds8',k:'heart',x:1250,y:25,w:25,r:21},
  {id:'ds9',k:'heart',x:1203,y:48,w:21,r:-23},
  {id:'ds10',k:'balloon',x:1628,y:782,w:26,r:10},
  {id:'ds11',k:'coffee',x:126,y:603,w:44,r:9},
  {id:'ds12',k:'sun',x:1500,y:702,w:44,r:0},
  {id:'ds13',k:'music',x:329,y:547,w:34,r:20},
  {id:'ds14',k:'sun',x:1524,y:16,w:44,r:0},
  {id:'ds15',k:'moon',x:5,y:59,w:44,r:-18}
];

var MOBILE=[
  {id:'ms1',k:'moon',x:16,y:55,w:33,r:-9},
  {id:'ms2',k:'star-malva',x:248,y:37,w:16,r:0},
  {id:'ms3',k:'star-azul',x:261,y:20,w:16,r:0},
  {id:'ms4',k:'sun',x:368,y:417,w:44,r:0},
  {id:'ms5',k:'sun',x:341,y:204,w:34,r:0},
  {id:'ms6',k:'bird',x:112,y:196,w:29,r:0},
  {id:'ms7',k:'sparkle',x:8,y:630,w:25,r:0}
];

var SVGS={
  heart:'<svg viewBox="0 0 32 32" fill="none"><path d="M16 28S3 19 3 11a7 7 0 0 1 13-3.5A7 7 0 0 1 29 11c0 8-13 17-13 17z" fill="#f9dce4" stroke="#e88a8a" stroke-width="1.5" stroke-linejoin="round"/></svg>',
  star:'<svg viewBox="0 0 32 32" fill="none"><polygon points="16,3 19.5,12.5 29,13 21.5,19.5 24,29 16,23 8,29 10.5,19.5 3,13 12.5,12.5" fill="#fce4a8" stroke="#e8b830" stroke-width="1.5" stroke-linejoin="round"/></svg>',
  'star-malva':'<svg viewBox="0 0 32 32" fill="none"><polygon points="16,3 19.5,12.5 29,13 21.5,19.5 24,29 16,23 8,29 10.5,19.5 3,13 12.5,12.5" fill="#d4bee8" stroke="#b9a8dc" stroke-width="1.5" stroke-linejoin="round"/></svg>',
  'star-azul':'<svg viewBox="0 0 32 32" fill="none"><polygon points="16,3 19.5,12.5 29,13 21.5,19.5 24,29 16,23 8,29 10.5,19.5 3,13 12.5,12.5" fill="#d4e8f5" stroke="#b0c4de" stroke-width="1.5" stroke-linejoin="round"/></svg>',
  sparkle:'<svg viewBox="0 0 32 32" fill="none" stroke="#e8b830" stroke-width="1.5" stroke-linecap="round"><path d="M16 2v6M16 24v6M2 16h6M24 16h6"/><circle cx="16" cy="16" r="2" fill="#fce4a8" stroke="none"/></svg>',
  sun:'<svg viewBox="0 0 32 32" fill="none"><circle cx="16" cy="16" r="6" fill="#fce4a8" stroke="#f0c840" stroke-width="1.5"/><g stroke="#f0c840" stroke-width="1.5" stroke-linecap="round"><line x1="16" y1="3" x2="16" y2="7"/><line x1="16" y1="25" x2="16" y2="29"/><line x1="3" y1="16" x2="7" y2="16"/><line x1="25" y1="16" x2="29" y2="16"/><line x1="6.8" y1="6.8" x2="9.6" y2="9.6"/><line x1="22.4" y1="22.4" x2="25.2" y2="25.2"/><line x1="6.8" y1="25.2" x2="9.6" y2="22.4"/><line x1="22.4" y1="9.6" x2="25.2" y2="6.8"/></g></svg>',
  moon:'<svg viewBox="0 0 32 32" fill="none"><path d="M20 6a10 10 0 1 0 6 18 8 8 0 0 1-6-18z" fill="#f0eaf8" stroke="#b9a8dc" stroke-width="1.5" stroke-linecap="round"/></svg>',
  rainbow:'<svg viewBox="0 0 32 32" fill="none"><path d="M4 24a12 12 0 0 1 24 0" stroke="#e88a8a" stroke-width="2" stroke-linecap="round"/><path d="M7 24a9 9 0 0 1 18 0" stroke="#f0c840" stroke-width="2" stroke-linecap="round"/><path d="M10 24a6 6 0 0 1 12 0" stroke="#8ecf8e" stroke-width="2" stroke-linecap="round"/><path d="M13 24a3 3 0 0 1 6 0" stroke="#9ac4e8" stroke-width="2" stroke-linecap="round"/></svg>',
  house:'<svg viewBox="0 0 32 32" fill="none"><path d="M4 16L16 5l12 11" stroke="#b9a8dc" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M7 14v13h18V14" fill="#d4bee8" stroke="#b9a8dc" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><rect x="13" y="20" width="6" height="7" rx="1" fill="#f5efe6" stroke="#b9a8dc" stroke-width="1.2"/><rect x="10" y="16" width="4" height="3.5" rx=".8" fill="#f5efe6" stroke="#b9a8dc" stroke-width="1"/><rect x="18" y="16" width="4" height="3.5" rx=".8" fill="#f5efe6" stroke="#b9a8dc" stroke-width="1"/></svg>',
  coffee:'<svg viewBox="0 0 32 32" fill="none"><path d="M6 12h16v11c0 2-2 4-4 4h-8c-2 0-4-2-4-4V12z" fill="#f5efe6" stroke="#b0c4de" stroke-width="1.5"/><path d="M22 15h2c1.7 0 3 1.3 3 3s-1.3 3-3 3h-2" stroke="#b0c4de" stroke-width="1.5" stroke-linecap="round"/><path d="M10 8c1-2 2-2 3 0" stroke="#d4bee8" stroke-width="1.2" stroke-linecap="round"/><path d="M14 7c1-2 2-2 3 0" stroke="#d4bee8" stroke-width="1.2" stroke-linecap="round"/><path d="M18 8c1-2 2-2 3 0" stroke="#d4bee8" stroke-width="1.2" stroke-linecap="round"/></svg>',
  balloon:'<svg viewBox="0 0 32 32" fill="none"><ellipse cx="16" cy="13" rx="8" ry="10" fill="#d4bee8" stroke="#b9a8dc" stroke-width="1.5"/><path d="M16 23l-1.5 3h3L16 23z" fill="#d4bee8" stroke="#b9a8dc" stroke-width="1"/><path d="M16 26c0 0-1 2-2 3" stroke="#b9a8dc" stroke-width="1" stroke-linecap="round"/><ellipse cx="13" cy="10" rx="2" ry="3" fill="rgba(255,255,255,.35)" stroke="none"/></svg>',
  music:'<svg viewBox="0 0 32 32" fill="none"><path d="M12 26V8l14-4v18" stroke="#b9a8dc" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><ellipse cx="10" cy="26" rx="4" ry="3" fill="#d4bee8" stroke="#b9a8dc" stroke-width="1.2"/><ellipse cx="24" cy="22" rx="4" ry="3" fill="#d4bee8" stroke="#b9a8dc" stroke-width="1.2"/></svg>',
  bird:'<svg viewBox="0 0 32 32" fill="none"><ellipse cx="14" cy="16" rx="8" ry="6" fill="#eaf2fa" stroke="#b0c4de" stroke-width="1.5"/><circle cx="10" cy="14" r="1.5" fill="#4a3f62"/><path d="M5 15l-3-1 3-2" stroke="#e8b830" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M18 13c3-4 7-3 8-1s-1 4-4 4" stroke="#b0c4de" stroke-width="1.2" stroke-linecap="round"/><path d="M10 22l-1 4 3-2" stroke="#b0c4de" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 22l1 4-3-2" stroke="#b0c4de" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
};

function isMobile(){return window.innerWidth<=480;}

function getStage(){
  return document.querySelector('.inicio-stage')||document.querySelector('.board-fit');
}

function render(){
  var stage=getStage();if(!stage)return;
  var existing=document.querySelector('.aht-prod-layer');
  if(existing)existing.remove();
  var rect=stage.getBoundingClientRect();
  var scrollY=window.pageYOffset||0;
  var scrollX=window.pageXOffset||0;
  var layer=document.createElement('div');
  layer.className='aht-prod-layer';
  layer.style.cssText='position:fixed;left:0;top:0;width:100vw;height:100vh;pointer-events:none;z-index:100;overflow:visible';
  var items=isMobile()?MOBILE:DESKTOP;
  items.forEach(function(s){
    var el=document.createElement('div');
    el.style.cssText='position:absolute;pointer-events:none;z-index:100;filter:drop-shadow(1px 2px 3px rgba(0,0,0,.18))';
    el.style.left=(rect.left+scrollX+s.x)+'px';
    el.style.top=(rect.top+scrollY+s.y)+'px';
    el.style.width=s.w+'px';el.style.height=s.w+'px';
    el.style.transform='rotate('+s.r+'deg)';
    el.innerHTML='<div style="width:100%;height:100%">'+(SVGS[s.k]||SVGS.heart)+'</div>';
    layer.appendChild(el);
  });
  document.body.appendChild(layer);
}

if(document.readyState==='loading'){
  document.addEventListener('DOMContentLoaded',function(){setTimeout(render,300);});
}else{
  setTimeout(render,300);
}
window.addEventListener('resize',function(){setTimeout(render,100);});
window.addEventListener('scroll',function(){setTimeout(render,50);});
new MutationObserver(function(){setTimeout(render,200);}).observe(document.body,{childList:true,subtree:true});
})();
