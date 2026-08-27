(function () {
  'use strict';
  if (new URLSearchParams(location.search).get('design') !== '1') return;
  var defs = [
    ['cabecera','.game-top'],['mapa','.game-map-wrap'],['pasar-rato','[data-pasar-rato]'],
    ['cotilleo','.obj-cotilleo-par'],['planes-en-curso','.obj-proximo-polaroid'],
    ['proximos-planes','.obj-planes-prox-block'],['mensajitos','.zona-actividad .obj-buzon'],
    ['vecinos','.obj-vecinos-resumen'],['nuevo-plan','.obj-nuevo-plan'],
    ['como-va-esto','.btn-guia'],['misiones','.shell-grupo-misiones-par'],
    ['parejas','.shell-grupo-parejas'],['columna-izquierda','.game-left'],
    ['columna-central','.game-map-wrap'],['columna-derecha','.game-right'],
    ['celestine-apunta','.obj-vecinos-resumen'],['hud','.game-top']
  ];
  var storage='aht_inicio_maqueta_v1', viewport=innerWidth<=720?'mobile':'desktop';
  var state=JSON.parse(localStorage.getItem(storage)||'{}'); state.mobile=state.mobile||{}; state.desktop=state.desktop||{};
  var tool=document.createElement('aside'); tool.className='aht-design-tool'; tool.innerHTML=
    '<div class="aht-design-head"><h2>Modo diseño · Inicio</h2><button type="button" data-act="collapse">−</button></div><div class="aht-design-body"><select aria-label="Elemento"></select>'+
    '<label>Ancho <output></output><input data-p="width" type="range"></label>'+
    '<label>Alto <output></output><input data-p="height" type="range"></label>'+
    '<label>Tamaño letra <output></output><input data-p="fontSize" type="range" min="8" max="48" step=".5"></label>'+
    '<label>Tamaño icono <output></output><input data-p="iconSize" type="range" min="8" max="160"></label>'+
    '<button data-act="reset">Reset elemento</button><button data-act="all">Reset maqueta</button>'+
    '<button data-act="copy">COPIAR AJUSTES</button><p>Arrastra el elemento seleccionado.<br>Maqueta: '+viewport+'</p></div>';
  document.body.appendChild(tool); var select=tool.querySelector('select');
  defs.forEach(function(d){var o=document.createElement('option');o.value=d[0];o.textContent=d[0];select.appendChild(o);});
  function el(){var d=defs.filter(function(x){return x[0]===select.value;})[0];return d&&document.querySelector(d[1]);}
  function cfg(){return state[viewport][select.value]||{};}
  function editableCfg(){return state[viewport][select.value]||(state[viewport][select.value]={});}
  function save(){localStorage.setItem(storage,JSON.stringify(state));}
  function iconTarget(e){return e.querySelector('img,.obj-buzon-ico-wrap,.obj-nuevo-plan-ico,.plan-seccion-ico')||null;}
  function textTarget(e){return e.querySelector('.game-left-tile-label,.obj-cotilleo-txt,.obj-proximo-tit,.pp-mov-tit,.enc-mov-tit,.zona-tit,.btn-guia')||e;}
  function apply(){var e=el(),c=state[viewport][select.value]||{};if(!e)return;e.classList.add('aht-design-selected');
    var bounds=e.parentElement?e.parentElement.getBoundingClientRect():{width:innerWidth,height:innerHeight}, maxW=Math.max(80,Math.round(Math.min(innerWidth*1.5,bounds.width*2||innerWidth*1.5))), maxH=Math.max(60,Math.round(Math.min(innerHeight*1.5,700)));
    [['width',e.offsetWidth,40,maxW],['height',e.offsetHeight,20,maxH],['fontSize',parseFloat(getComputedStyle(textTarget(e)).fontSize)||16,8,64]].forEach(function(x){var i=tool.querySelector('[data-p="'+x[0]+'"]'),o=i.previousElementSibling,v=c[x[0]]==null?x[1]:c[x[0]];i.min=x[2];i.max=x[3];i.value=Math.min(x[3],Math.max(x[2],v));o.value=Math.round(v*10)/10+'px';});
    var icon=iconTarget(e),ii=tool.querySelector('[data-p="iconSize"]'),io=ii.previousElementSibling;ii.disabled=!icon;ii.min=8;ii.max=Math.max(48,Math.round(Math.min(innerWidth*.7,160)));ii.value=c.iconSize|| (icon?icon.offsetWidth:8);io.value=icon?(ii.value+'px'):'no disponible';
    if(c.width!=null)e.style.setProperty('width',c.width+'px','important');if(c.height!=null)e.style.setProperty('height',c.height+'px','important');if(c.fontSize!=null)textTarget(e).style.setProperty('font-size',c.fontSize+'px','important');e.style.translate=(c.x||0)+'px '+(c.y||0)+'px';if(icon&&c.iconSize!=null)icon.style.setProperty('width',c.iconSize+'px','important');
  }
  select.addEventListener('change',function(){document.querySelectorAll('.aht-design-selected').forEach(function(e){e.classList.remove('aht-design-selected');});apply();});
  tool.querySelectorAll('input[data-p]').forEach(function(i){i.addEventListener('input',function(){if(i.disabled)return;editableCfg()[i.dataset.p]=Number(i.value);save();apply();});});
  tool.addEventListener('click',function(e){var a=e.target.dataset.act;if(a==='collapse'){tool.classList.toggle('is-collapsed');localStorage.setItem(storage+'_collapsed',tool.classList.contains('is-collapsed')?'1':'0');}if(a==='reset'){delete state[viewport][select.value];save();apply();}if(a==='all'){state[viewport]={};save();apply();}if(a==='copy'){var s='';['desktop','mobile'].forEach(function(v){s+=v.toUpperCase()+'\n';Object.keys(state[v]).forEach(function(n){s+=n+':\n';Object.keys(state[v][n]).forEach(function(p){s+='  '+p.replace('fontSize','font-size').replace('iconSize','icon-size')+': '+state[v][n][p]+'px\n';});});s+='\n';});if(navigator.clipboard)navigator.clipboard.writeText(s);alert(s);}});
  var drag=null;tool.querySelector('.aht-design-head').addEventListener('pointerdown',function(e){var r=tool.getBoundingClientRect();drag={x:e.clientX,y:e.clientY,ox:r.left,oy:r.top};tool.setPointerCapture(e.pointerId);});
  tool.addEventListener('pointermove',function(e){if(!drag)return;tool.style.left=Math.max(0,drag.ox+e.clientX-drag.x)+'px';tool.style.top=Math.max(0,drag.oy+e.clientY-drag.y)+'px';tool.style.right='auto';tool.style.bottom='auto';});
  tool.addEventListener('pointerup',function(){drag=null;});
  document.addEventListener('pointerdown',function(e){var t=el();if(!t||tool.contains(e.target)||(!t.contains(e.target)&&e.target!==t))return;var c=editableCfg();drag={x:e.clientX,y:e.clientY,ox:c.x||0,oy:c.y||0};});
  document.addEventListener('pointermove',function(e){if(!drag||tool.contains(e.target))return;var c=editableCfg();c.x=drag.ox+e.clientX-drag.x;c.y=drag.oy+e.clientY-drag.y;save();apply();});document.addEventListener('pointerup',function(){drag=null;});
  document.addEventListener('click',function(e){if(tool.contains(e.target))return;var d=defs.filter(function(x){var t=document.querySelector(x[1]);return t&&t.contains(e.target);})[0];if(!d)return;e.preventDefault();e.stopPropagation();select.value=d[0];document.querySelectorAll('.aht-design-selected').forEach(function(t){t.classList.remove('aht-design-selected');});apply();},true);
  if(localStorage.getItem(storage+'_collapsed')==='1')tool.classList.add('is-collapsed');document.body.classList.add('aht-design-mode');apply();
}());
