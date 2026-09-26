// Logo springt (en maakt geluid) bij een klik; elke sprong net iets anders.
(function () {
  const logo = document.getElementById('logo');
  if (!logo || !logo.hasAttribute('data-animate')) return;

  const sounds = [document.getElementById('toing1'), document.getElementById('toing2')];

  logo.addEventListener('click', () => {
    const height = 30 + Math.random() * 30;                                 // 30% - 60% omhoog
    const drift = (Math.random() - 0.5) * 24;                               // -12% .. 12% zijwaarts
    const rotate = (Math.random() < 0.5 ? -1 : 1) * (5 + Math.random() * 10); // ±5..15deg

    logo.style.setProperty('--jump-height', `-${height}%`);
    logo.style.setProperty('--jump-drift', `${drift}%`);
    logo.style.setProperty('--jump-rotate', `${rotate}deg`);

    // Animatie herstarten, ook als hij nog bezig was
    logo.classList.remove('jump');
    void logo.offsetWidth;
    logo.classList.add('jump');

    const sound = Math.random() < 0.75 ? sounds[0] : sounds[1];
    if (sound) {
      sound.currentTime = 0;
      sound.play().catch(() => {});
    }
  });
})();
