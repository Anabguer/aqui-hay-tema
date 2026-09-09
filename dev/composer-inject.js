/**
 * composer-inject.js — runs inside play.php iframe when ?composer=1
 * Handles: click-to-place, drag, resize, rotate, protected zones
 * Communicates with parent via postMessage
 */
(function(){
'use strict';

/* ── Scoped CSS injection for composer layout ── */
(function injectComposerCSS(){
  var link=document.createElement('link');
  link.rel='stylesheet';
  link.href='dev/composer-layout.css';
  document.head.appendChild(link);
})();

var PARENT='*';
var STAGE=null;
var placeMode=false;
var stickers={};
var protShow=false;
var drag=null;

function findStage(){
  STAGE=document.querySelector('.inicio-stage');
  return STAGE;
}

function stageRect(){
  if(!STAGE)findStage();
  if(!STAGE)return null;
  return STAGE.getBoundingClientRect();
}

/* ── Protected zones (selectors + labels) ── */
var PZ_DESKTOP=[
  ['section.inicio-desktop .game-top','Cabecera'],['.inicio-map-host','Mapa'],
  ['section.inicio-desktop .obj-buzon','Mensajitos'],
  ['section.inicio-desktop .obj-vecinos-resumen','Vecinos'],
  ['section.inicio-desktop .obj-nuevo-plan','Plan'],
  ['.obj-cotilleo-par','Cotilleo'],
  ['.shell-grupo-parejas','Parejas']
];
var PZ_MOBILE=[
  ['section.inicio-mobile .game-top','Cabecera'],['.inicio-map-host','Mapa'],
  ['section.inicio-mobile .obj-buzon','Mensajitos'],
  ['section.inicio-mobile .obj-vecinos-resumen','Vecinos'],
  ['section.inicio-mobile .obj-nuevo-plan','Plan']
];

function getPzDefs(){
  var isMobile=window.innerWidth<=768;
  return isMobile?PZ_MOBILE:PZ_DESKTOP;
}

function renderProt(){
  document.querySelectorAll('.aht-pzone,.aht-plbl').forEach(function(e){e.remove();});
  if(!protShow||!STAGE)return;
  var sr=stageRect();if(!sr)return;
  var defs=getPzDefs();
  defs.forEach(function(d){
    var el=STAGE.querySelector(d[0]);if(!el)return;
    var r=el.getBoundingClientRect();
    var z=document.createElement('div');
    z.className='aht-pzone';
    z.style.cssText='position:absolute;pointer-events:none;border:2px dashed rgba(212,168,67,.55);background:rgba(212,168,67,.08);border-radius:4px;z-index:9998;left:'+(r.left-sr.left)+'px;top:'+(r.top-sr.top)+'px;width:'+r.width+'px;height:'+r.height+'px';
    STAGE.appendChild(z);
    var lb=document.createElement('div');
    lb.className='aht-plbl';
    lb.style.cssText='position:absolute;top:2px;left:4px;font-size:9px;color:#d4a843;pointer-events:none;white-space:nowrap;z-index:9999;left:'+(r.left-sr.left+4)+'px;top:'+(r.top-sr.top+2)+'px';
    lb.textContent=d[1];
    STAGE.appendChild(lb);
  });
}

/* ── Sticker layer inside stage ── */
function getLayer(){
  var l=STAGE.querySelector('.aht-sticker-layer');
  if(!l){
    l=document.createElement('div');
    l.className='aht-sticker-layer';
    l.style.cssText='position:absolute;inset:0;pointer-events:none;z-index:9997';
    STAGE.appendChild(l);
  }
  return l;
}

function addSticker(data){
  if(!STAGE)findStage();if(!STAGE)return;
  var layer=getLayer();
  if(stickers[data.id]){removeSticker(data.id);}
  var el=document.createElement('div');
  el.className='aht-stk';
  el.dataset.id=data.id;
  el.style.cssText='position:absolute;pointer-events:all;cursor:move;touch-action:none;user-select:none;z-index:9997;filter:drop-shadow(1px 2px 3px rgba(0,0,0,.18))';
  el.style.left=data.x+'px';el.style.top=data.y+'px';
  el.style.width=data.width+'px';el.style.height=data.width+'px';
  el.style.transform='rotate('+data.rotation+'deg)';

  var svg=window.AHT_STICKER_SVGS&&window.AHT_STICKER_SVGS[data.sticker];
  if(!svg){
    var fallback={
      heart:'<svg viewBox="0 0 32 32" fill="none"><path d="M16 28S3 19 3 11a7 7 0 0 1 13-3.5A7 7 0 0 1 29 11c0 8-13 17-13 17z" fill="#f9dce4" stroke="#e88a8a" stroke-width="1.5" stroke-linejoin="round"/></svg>',
      star:'<svg viewBox="0 0 32 32" fill="none"><polygon points="16,3 19.5,12.5 29,13 21.5,19.5 24,29 16,23 8,29 10.5,19.5 3,13 12.5,12.5" fill="#fce4a8" stroke="#e8b830" stroke-width="1.5" stroke-linejoin="round"/></svg>',
      flower:'<svg viewBox="0 0 32 32" fill="none"><circle cx="16" cy="14" r="3" fill="#fce4a8" stroke="#e8b830" stroke-width="1"/><g fill="#f5c6d0" stroke="#e88a8a" stroke-width="1"><ellipse cx="16" cy="7" rx="3" ry="4"/><ellipse cx="22" cy="11" rx="3" ry="4" transform="rotate(60 22 11)"/><ellipse cx="22" cy="17" rx="3" ry="4" transform="rotate(120 22 17)"/><ellipse cx="16" cy="21" rx="3" ry="4" transform="rotate(180 16 21)"/><ellipse cx="10" cy="17" rx="3" ry="4" transform="rotate(240 10 17)"/><ellipse cx="10" cy="11" rx="3" ry="4" transform="rotate(300 10 11)"/></g></svg>',
      sparkle:'<svg viewBox="0 0 32 32" fill="none" stroke="#e8b830" stroke-width="1.5" stroke-linecap="round"><path d="M16 2v6M16 24v6M2 16h6M24 16h6"/><circle cx="16" cy="16" r="2" fill="#fce4a8" stroke="none"/></svg>',
      leaf:'<svg viewBox="0 0 32 32" fill="none"><path d="M8 28c0 0 1-12 8-18s14-3 14-3-1 12-8 18-14 3-14 3z" fill="#c8e6c0" stroke="#6aaa5a" stroke-width="1.5" stroke-linecap="round"/></svg>',
      cloud:'<svg viewBox="0 0 32 32" fill="none"><path d="M8 22c-2.5 0-4-2-4-4.5S5.5 13 8 13c.3-2.8 2.8-5 6-5 2.8 0 5.2 1.7 5.8 4.2.5-.2 1-.3 1.5-.3 2.5 0 4.5 2 4.5 4.5S23.8 21 21.3 21H8z" fill="#eaf2fa" stroke="#b0c4de" stroke-width="1.5" stroke-linecap="round"/></svg>',
      sun:'<svg viewBox="0 0 32 32" fill="none"><circle cx="16" cy="16" r="6" fill="#fce4a8" stroke="#f0c840" stroke-width="1.5"/><g stroke="#f0c840" stroke-width="1.5" stroke-linecap="round"><line x1="16" y1="3" x2="16" y2="7"/><line x1="16" y1="25" x2="16" y2="29"/><line x1="3" y1="16" x2="7" y2="16"/><line x1="25" y1="16" x2="29" y2="16"/><line x1="6.8" y1="6.8" x2="9.6" y2="9.6"/><line x1="22.4" y1="22.4" x2="25.2" y2="25.2"/><line x1="6.8" y1="25.2" x2="9.6" y2="22.4"/><line x1="22.4" y1="9.6" x2="25.2" y2="6.8"/></g></svg>',
      moon:'<svg viewBox="0 0 32 32" fill="none"><path d="M20 6a10 10 0 1 0 6 18 8 8 0 0 1-6-18z" fill="#f0eaf8" stroke="#b9a8dc" stroke-width="1.5" stroke-linecap="round"/></svg>',
      rainbow:'<svg viewBox="0 0 32 32" fill="none"><path d="M4 24a12 12 0 0 1 24 0" stroke="#e88a8a" stroke-width="2" stroke-linecap="round"/><path d="M7 24a9 9 0 0 1 18 0" stroke="#f0c840" stroke-width="2" stroke-linecap="round"/><path d="M10 24a6 6 0 0 1 12 0" stroke="#8ecf8e" stroke-width="2" stroke-linecap="round"/><path d="M13 24a3 3 0 0 1 6 0" stroke="#9ac4e8" stroke-width="2" stroke-linecap="round"/></svg>',
      spiral:'<svg viewBox="0 0 32 32" fill="none" stroke="#b9a8dc" stroke-width="1.5" stroke-linecap="round"><path d="M16 16c0-2 2-4 4-4s4 2 4 4-3 6-7 6-9-4-9-8 4-8 8-8 11 4 11 8-5 10-11 10"/></svg>',
      house:'<svg viewBox="0 0 32 32" fill="none"><path d="M4 16L16 5l12 11" stroke="#b9a8dc" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M7 14v13h18V14" fill="#d4bee8" stroke="#b9a8dc" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><rect x="13" y="20" width="6" height="7" rx="1" fill="#f5efe6" stroke="#b9a8dc" stroke-width="1.2"/><rect x="10" y="16" width="4" height="3.5" rx=".8" fill="#f5efe6" stroke="#b9a8dc" stroke-width="1"/><rect x="18" y="16" width="4" height="3.5" rx=".8" fill="#f5efe6" stroke="#b9a8dc" stroke-width="1"/></svg>',
      coffee:'<svg viewBox="0 0 32 32" fill="none"><path d="M6 12h16v11c0 2-2 4-4 4h-8c-2 0-4-2-4-4V12z" fill="#f5efe6" stroke="#b0c4de" stroke-width="1.5"/><path d="M22 15h2c1.7 0 3 1.3 3 3s-1.3 3-3 3h-2" stroke="#b0c4de" stroke-width="1.5" stroke-linecap="round"/><path d="M10 8c1-2 2-2 3 0" stroke="#d4bee8" stroke-width="1.2" stroke-linecap="round"/><path d="M14 7c1-2 2-2 3 0" stroke="#d4bee8" stroke-width="1.2" stroke-linecap="round"/><path d="M18 8c1-2 2-2 3 0" stroke="#d4bee8" stroke-width="1.2" stroke-linecap="round"/></svg>',
      envelope:'<svg viewBox="0 0 32 32" fill="none"><rect x="3" y="8" width="26" height="17" rx="2.5" fill="#f5efe6" stroke="#b0c4de" stroke-width="1.5" stroke-linejoin="round"/><path d="M3 10l13 8 13-8" stroke="#b0c4de" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><circle cx="24" cy="11" r="3" fill="#e88a8a"/><path d="M24 9.5v1.5h1.5" stroke="#fff" stroke-width=".8" stroke-linecap="round"/></svg>',
      balloon:'<svg viewBox="0 0 32 32" fill="none"><ellipse cx="16" cy="13" rx="8" ry="10" fill="#d4bee8" stroke="#b9a8dc" stroke-width="1.5"/><path d="M16 23l-1.5 3h3L16 23z" fill="#d4bee8" stroke="#b9a8dc" stroke-width="1"/><path d="M16 26c0 0-1 2-2 3" stroke="#b9a8dc" stroke-width="1" stroke-linecap="round"/><ellipse cx="13" cy="10" rx="2" ry="3" fill="rgba(255,255,255,.35)" stroke="none"/></svg>',
      butterfly:'<svg viewBox="0 0 32 32" fill="none"><path d="M16 8v18" stroke="#8b6c42" stroke-width="1.5" stroke-linecap="round"/><path d="M16 12c-6-5-12-2-10 4s8 4 10 1" fill="#d4bee8" stroke="#b9a8dc" stroke-width="1.2" stroke-linejoin="round"/><path d="M16 12c6-5 12-2 10 4s-8 4-10 1" fill="#f5c6d0" stroke="#e88a8a" stroke-width="1.2" stroke-linejoin="round"/><path d="M16 18c-4 2-8 1-7-2s5-3 7-1" fill="#d4bee8" stroke="#b9a8dc" stroke-width="1"/><path d="M16 18c4 2 8 1 7-2s-5-3-7-1" fill="#f5c6d0" stroke="#e88a8a" stroke-width="1"/><path d="M14 7c-1-2 0-3 2-2M18 7c1-2 0-3-2-2" stroke="#8b6c42" stroke-width="1" stroke-linecap="round"/></svg>',
      music:'<svg viewBox="0 0 32 32" fill="none"><path d="M12 26V8l14-4v18" stroke="#b9a8dc" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><ellipse cx="10" cy="26" rx="4" ry="3" fill="#d4bee8" stroke="#b9a8dc" stroke-width="1.2"/><ellipse cx="24" cy="22" rx="4" ry="3" fill="#d4bee8" stroke="#b9a8dc" stroke-width="1.2"/></svg>',
      bolt:'<svg viewBox="0 0 32 32" fill="none"><path d="M18 3L8 18h6l-2 11 10-15h-6l2-11z" fill="#fce4a8" stroke="#e8b830" stroke-width="1.5" stroke-linejoin="round"/></svg>',
      thought:'<svg viewBox="0 0 32 32" fill="none"><path d="M4 6c0-1.7 1.3-3 3-3h18c1.7 0 3 1.3 3 3v12c0 1.7-1.3 3-3 3H12l-5 4v-4H7c-1.7 0-3-1.3-3-3V6z" fill="#f5f0e8" stroke="#b9a8dc" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><circle cx="11" cy="12" r="1.2" fill="#b9a8dc"/><circle cx="16" cy="12" r="1.2" fill="#b9a8dc"/><circle cx="21" cy="12" r="1.2" fill="#b9a8dc"/></svg>',
      sunflower:'<svg viewBox="0 0 32 32" fill="none"><circle cx="16" cy="14" r="4" fill="#8b6c42" stroke="#6b4c22" stroke-width="1"/><g fill="#f8d44a" stroke="#e8b830" stroke-width=".8"><ellipse cx="16" cy="7" rx="2.5" ry="3.5"/><ellipse cx="21" cy="9" rx="2.5" ry="3.5" transform="rotate(40 21 9)"/><ellipse cx="23" cy="14" rx="2.5" ry="3.5" transform="rotate(80 23 14)"/><ellipse cx="21" cy="19" rx="2.5" ry="3.5" transform="rotate(120 21 19)"/><ellipse cx="16" cy="21" rx="2.5" ry="3.5" transform="rotate(160 16 21)"/><ellipse cx="11" cy="19" rx="2.5" ry="3.5" transform="rotate(200 11 19)"/><ellipse cx="9" cy="14" rx="2.5" ry="3.5" transform="rotate(240 9 14)"/><ellipse cx="11" cy="9" rx="2.5" ry="3.5" transform="rotate(300 11 9)"/></g></svg>',
      bird:'<svg viewBox="0 0 32 32" fill="none"><ellipse cx="14" cy="16" rx="8" ry="6" fill="#eaf2fa" stroke="#b0c4de" stroke-width="1.5"/><circle cx="10" cy="14" r="1.5" fill="#4a3f62"/><path d="M5 15l-3-1 3-2" stroke="#e8b830" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M18 13c3-4 7-3 8-1s-1 4-4 4" stroke="#b0c4de" stroke-width="1.2" stroke-linecap="round"/><path d="M10 22l-1 4 3-2" stroke="#b0c4de" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 22l1 4-3-2" stroke="#b0c4de" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
    };
    svg=fallback[data.sticker]||fallback.heart;
  }
  el.innerHTML='<div class="aht-stk-inner" style="width:100%;height:100%;pointer-events:none">'+svg+'</div>';

  /* handles */
  el.innerHTML+='<div class="aht-h aht-h-tl" style="position:absolute;width:10px;height:10px;background:#c45b72;border:1px solid #fff;border-radius:50%;top:-5px;left:-5px;cursor:nwse-resize;pointer-events:all;display:none"></div>';
  el.innerHTML+='<div class="aht-h aht-h-br" style="position:absolute;width:10px;height:10px;background:#c45b72;border:1px solid #fff;border-radius:50%;bottom:-5px;right:-5px;cursor:nwse-resize;pointer-events:all;display:none"></div>';
  el.innerHTML+='<div class="aht-h aht-h-rot" style="position:absolute;width:10px;height:10px;background:#4a7fb5;border:1px solid #fff;border-radius:50%;top:-22px;left:50%;transform:translateX(-50%);cursor:grab;pointer-events:all;display:none"></div>';

  layer.appendChild(el);
  stickers[data.id]={el:el,data:Object.assign({},data)};
  wireSticker(el);
}

function removeSticker(id){
  var s=stickers[id];if(!s)return;
  s.el.remove();delete stickers[id];
}

function getScale(){
  try{
    var f=window.frameElement;
    if(!f)return 1;
    var iw=f.clientWidth,ih=f.clientHeight;
    var sw=window.innerWidth||1,sh=window.innerHeight||1;
    if(iw>0&&ih>0)return sw/iw;
  }catch(e){}
  return 1;
}

function wireSticker(el){
  el.addEventListener('pointerdown',function(e){
    var h=e.target.closest('.aht-h');
    if(h){
      e.stopPropagation();e.preventDefault();
      var isRot=h.classList.contains('aht-h-rot');
      drag={
        type:isRot?'rot':h.classList.contains('aht-h-tl')?'tl':'br',
        el:el,sx:e.clientX,sy:e.clientY,
        ox:parseFloat(el.style.left)||0,oy:parseFloat(el.style.top)||0,
        ow:parseFloat(el.style.width)||44,
        or:parseFloat(el.style.transform.replace(/[^-\d.]/g,''))||0,
        sc:getScale()
      };
      el.setPointerCapture(e.pointerId);
      return;
    }
    e.stopPropagation();e.preventDefault();
    selectSticker(el);
    drag={type:'mv',el:el,sx:e.clientX,sy:e.clientY,ox:parseFloat(el.style.left)||0,oy:parseFloat(el.style.top)||0,sc:getScale()};
    el.setPointerCapture(e.pointerId);
  });

  el.addEventListener('pointermove',function(e){
    if(!drag||drag.el!==el)return;
    var dx=(e.clientX-drag.sx)/drag.sc,dy=(e.clientY-drag.sy)/drag.sc;
    if(drag.type==='mv'){
      el.style.left=(drag.ox+dx)+'px';el.style.top=(drag.oy+dy)+'px';
    }else if(drag.type==='br'){
      el.style.width=Math.max(16,drag.ow+dx)+'px';el.style.height=el.style.width;
    }else if(drag.type==='tl'){
      var nw=Math.max(16,drag.ow-dx);
      el.style.width=nw+'px';el.style.height=nw+'px';
      el.style.left=(drag.ox+(drag.ow-nw))+'px';
      el.style.top=(drag.oy+(drag.ow-nw))+'px';
    }else if(drag.type==='rot'){
      var rc=el.getBoundingClientRect(),cx=rc.left+rc.width/2,cy=rc.top+rc.height/2;
      var angle=Math.atan2(e.clientY-cy,e.clientX-cx)*180/Math.PI+90;
      el.style.transform='rotate('+Math.round(angle)+'deg)';
    }
  });

  el.addEventListener('pointerup',function(){
    if(drag){
      var sid=el.dataset.id;
      parent.postMessage({type:'aht-sticker-moved',id:sid,
        x:parseFloat(el.style.left),y:parseFloat(el.style.top),
        width:parseFloat(el.style.width),
        rotation:parseFloat(el.style.transform.replace(/[^-\d.]/g,''))||0
      },PARENT);
    }
    drag=null;
  });

  el.addEventListener('dblclick',function(e){
    e.stopPropagation();e.preventDefault();
    parent.postMessage({type:'aht-sticker-moved',id:el.dataset.id,removed:true},PARENT);
    removeSticker(el.dataset.id);
  });
}

function selectSticker(el){
  Object.values(stickers).forEach(function(s){
    s.el.querySelectorAll('.aht-h').forEach(function(h){h.style.display='none';});
    s.el.style.outline='none';
  });
  el.querySelectorAll('.aht-h').forEach(function(h){h.style.display='block';});
  el.style.outline='2px dashed #c45b72';el.style.outlineOffset='2px';
}

/* ── Click interception (capture phase, before game handlers) ── */
function wireClicks(){
  if(!STAGE)findStage();if(!STAGE)return;
  STAGE.addEventListener('pointerdown',function(e){
    if(drag)return;
    if(e.target.closest('.aht-stk'))return;
    if(!placeMode)return;
    e.stopPropagation();e.preventDefault();
    var sr=stageRect();if(!sr)return;
    var sc=getScale();
    var x=(e.clientX-sr.left)/sc;
    var y=(e.clientY-sr.top)/sc;
    parent.postMessage({type:'aht-click',x:Math.round(x),y:Math.round(y)},PARENT);
  },true);
}

/* ── Message handler ── */
window.addEventListener('message',function(ev){
  var d=ev.data;if(!d||!d.type)return;
  switch(d.type){
    case 'aht-place-mode':placeMode=!!d.on;break;
    case 'aht-add-sticker':addSticker(d);break;
    case 'aht-remove-sticker':removeSticker(d.id);break;
    case 'aht-prot-zones':protShow=!!d.show;renderProt();break;
  }
});

/* ── Init ── */
function init(){
  if(!findStage()){
    setTimeout(init,200);return;
  }
  wireClicks();
  parent.postMessage({type:'aht-ready'},PARENT);
}

if(document.readyState==='loading'){
  document.addEventListener('DOMContentLoaded',function(){setTimeout(init,100);});
}else{
  setTimeout(init,100);
}

})();
