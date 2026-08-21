/* eslint-disable */
const fs = require('fs');
const path = require('path');
const file = path.join(__dirname, '..', 'assets', 'js', 'play-v3.js');
let s = fs.readFileSync(file, 'utf8');

if (!s.includes('pendPlan = ev.target')) {
  s = s.replace(
    '    const open = ev.target.closest(\'[data-open]\');',
    `    const pendPlan = ev.target.closest('[data-planes-pend]');
    if (pendPlan) {
      abrirAgendaPlanes(null);
      return;
    }
    const notifBtn = ev.target.closest('[data-plan-notif-btn]');
    if (notifBtn) {
      hidePlanNotif();
      abrirAgendaPlanes(planNotifEncId);
      return;
    }
    const open = ev.target.closest('[data-open]');`
  );
  fs.writeFileSync(file, s);
  console.log('click handlers added');
} else {
  console.log('already ok');
}
