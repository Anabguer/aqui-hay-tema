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
    '<h2>Modo diseño · Inicio</h2><select aria-label="Elemento"></select>'+
    '<label>Ancho <output></output><input data-p="width" type="range" min="40" max="900"></label>'+
    '<label>Alto <output></output><input data-p="height" type="range" min="20" max="700"></label>'+
    '<label>Tamaño letra <output></output><input data-p="fontSize" type="range" min="8" max="48" step=".5"></label>'+
    '<label>Tamaño icono <output></output><input data-p="iconSize" type="range" min="8" max="160"></label>'+
    '<button data-act="reset">Reset elemento</button><button data-act="all">Reset maqueta</button>'+
    '<button data-act="copy">COPIAR AJUSTES</button><p>Arrastra el elemento seleccionado.<br>Maqueta: '+viewport+'</p>';
  document.body.appendChild(tool); var select=tool.querySelector('select');
  defs.forEach(function(d){var o=document.createElement('option');o.value=d[0];o.textContent=d[0];select.appendChild(o);});
  function el(){var d=defs.filter(function(x){return x[0]===select.value;})[0];return d&&document.querySelector(d[1]);}
  function cfg(){return state[viewport][select.value]||(state[viewport][select.value]={});}
  function save(){localStorage.setItem(storage,JSON.stringify(state));}
  function apply(){var e=el(),c=cfg();if(!e)return;e.classList.add('aht-design-selected');
    ['width','height','fontSize','iconSize'].forEach(function(p){var i=tool.querySelector('[data-p="'+p+'"]'),v=c[p]||(p==='fontSize'?parseFloat(getComputedStyle(e).fontSize):p==='width'?e.offsetWidth:p==='height'?e.offsetHeight:48);i.value=v;i.nextElementSibling.value=Math.round(v*10)/10+'px';});
    e.style.width=c.width?c.width+'px':'';e.style.height=c.height?c.height+'px':'';e.style.fontSize=c.fontSize?c.fontSize+'px':'';e.style.translate=(c.x||0)+'px '+(c.y||0)+'px';
    e.querySelectorAll('img,.obj-buzon-ico-wrap,.obj-nuevo-plan-ico').forEach(function(i){i.style.width=c.iconSize?c.iconSize+'px':'';});
  }
  select.addEventListener('change',function(){document.querySelectorAll('.aht-design-selected').forEach(function(e){e.classList.remove('aht-design-selected');});apply();});
  tool.querySelectorAll('input[data-p]').forEach(function(i){i.addEventListener('input',function(){cfg()[i.dataset.p]=Number(i.value);save();apply();});});
  tool.addEventListener('click',function(e){var a=e.target.dataset.act;if(a==='reset'){delete state[viewport][select.value];save();apply();}if(a==='all'){state[viewport]={};save();apply();}if(a==='copy'){var s='';['desktop','mobile'].forEach(function(v){s+=v.toUpperCase()+'\n';Object.keys(state[v]).forEach(function(n){s+=n+':\n';Object.keys(state[v][n]).forEach(function(p){s+='  '+p.replace('fontSize','font-size').replace('iconSize','icon-size')+': '+state[v][n][p]+'px\n';});});s+='\n';});if(navigator.clipboard)navigator.clipboard.writeText(s);alert(s);}});
  var drag=null;document.addEventListener('pointerdown',function(e){var t=el();if(!t||tool.contains(e.target)||(!t.contains(e.target)&&e.target!==t))return;var c=cfg();drag={x:e.clientX,y:e.clientY,ox:c.x||0,oy:c.y||0};});
  document.addEventListener('pointermove',function(e){if(!drag)return;var c=cfg();c.x=drag.ox+e.clientX-drag.x;c.y=drag.oy+e.clientY-drag.y;save();apply();});document.addEventListener('pointerup',function(){drag=null;});
  document.addEventListener('click',function(e){if(tool.contains(e.target))return;var d=defs.filter(function(x){var t=document.querySelector(x[1]);return t&&t.contains(e.target);})[0];if(!d)return;e.preventDefault();e.stopPropagation();select.value=d[0];document.querySelectorAll('.aht-design-selected').forEach(function(t){t.classList.remove('aht-design-selected');});apply();},true);
  document.body.classList.add('aht-design-mode');apply();
}());
