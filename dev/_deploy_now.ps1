$files = @('play.php', 'assets/js/play-v3.js', 'assets/css/play-v3-organizar.css', 'assets/css/play-v3-responsive.css', 'assets/css/play-v3-consulta-edificio-v2.css', 'assets/aht-cache-buster.txt', 'aht-cache-buster.txt')
& 'W:\juegos\aqui-hay-tema\scripts\deploy_surgical.ps1' -Files $files -SkipVisualCheck -AutoConfirm
