# Rango RGB seguro (filtro producción)
Inseguro → transparente si:
  - R,G,B todos >= 210
  - luma >= 230 y min >= 195
  - luma >= 225 y saturación baja (max-min <= 25)

Claros SEGUROS usados:
  crema (228,200,155)  vainilla (235,205,130)  arena (220,185,130)
  turquesa fachada ~ (70,170,175)  rosa toldo ~ (230,150,170)
Nunca los 3 canales >= 205 a la vez.
