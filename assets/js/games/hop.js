/* Bunny Hop — endless runner. Jump over office obstacles, collect carrots. */
MeowArcade.register("hop", function (api) {
  "use strict";

  var c = api.canvas(720, 300);
  var ctx = c.ctx, W = c.W, H = c.H;
  var GROUND = 250;
  var BUNNY_X = 120;
  var GRAVITY = 2300;
  var OBSTACLES = [
    { emoji: "🌵", w: 30, h: 42, size: 46 },
    { emoji: "📦", w: 38, h: 36, size: 42 },
    { emoji: "🪴", w: 34, h: 44, size: 48 },
    { emoji: "📦", w: 38, h: 72, size: 42, stack: 2 }
  ];

  var s, raf = 0, last = 0;

  function reset() {
    s = {
      y: GROUND, vy: 0, jumps: 0, t: 0, speed: 320, dist: 0, carrots: 0,
      obstacles: [], carrotsOnScreen: [], nextObstacle: 420, pops: [],
      clouds: [{ x: 80, y: 50, r: 22 }, { x: 330, y: 80, r: 16 }, { x: 560, y: 42, r: 26 }]
    };
  }

  function score() { return Math.floor(s.dist / 20) + s.carrots * 10; }

  function jump() {
    if (!api.isPlaying() || s.jumps >= 2) return;
    s.vy = s.jumps === 0 ? -790 : -640;
    s.jumps++;
  }

  function spawn() {
    var kind = s.t < 12 ? OBSTACLES[Math.floor(Math.random() * 3)] : api.pick(OBSTACLES);
    s.obstacles.push({ x: W + 40, kind: kind });
    if (Math.random() < 0.6) {
      var high = Math.random() < 0.5;
      s.carrotsOnScreen.push({ x: W + 40 + api.rand(110, 220), y: GROUND - (high ? api.rand(110, 150) : api.rand(30, 60)), bob: Math.random() * 6 });
    }
    s.nextObstacle = api.rand(260, 470) + s.speed * 0.35;
  }

  function update(dt) {
    s.t += dt;
    s.speed = Math.min(780, 320 + s.t * 9);
    var move = s.speed * dt;
    s.dist += move;

    s.vy += GRAVITY * dt;
    s.y += s.vy * dt;
    if (s.y >= GROUND) { s.y = GROUND; s.vy = 0; s.jumps = 0; }

    s.nextObstacle -= move;
    if (s.nextObstacle <= 0) spawn();

    var top = s.y - 50, bottom = s.y - 6, left = BUNNY_X - 18, right = BUNNY_X + 18;
    for (var i = s.obstacles.length - 1; i >= 0; i--) {
      var o = s.obstacles[i];
      o.x -= move;
      if (o.x < -60) { s.obstacles.splice(i, 1); continue; }
      var ox1 = o.x - o.kind.w / 2 + 4, ox2 = o.x + o.kind.w / 2 - 4, oy = GROUND - o.kind.h + 6;
      if (right > ox1 && left < ox2 && bottom > oy) {
        draw();
        api.over(score(), "bonked a " + (o.kind.emoji === "🌵" ? "cactus" : o.kind.emoji === "🪴" ? "plant" : "box"));
        return;
      }
    }
    for (var j = s.carrotsOnScreen.length - 1; j >= 0; j--) {
      var k = s.carrotsOnScreen[j];
      k.x -= move;
      k.bob += dt * 5;
      if (Math.abs(k.x - BUNNY_X) < 30 && Math.abs(k.y - (s.y - 28)) < 34) {
        s.carrots++;
        s.pops.push({ x: k.x, y: k.y, life: 0.8 });
        s.carrotsOnScreen.splice(j, 1);
      } else if (k.x < -40) {
        s.carrotsOnScreen.splice(j, 1);
      }
    }
    s.pops.forEach(function (p) { p.life -= dt; p.y -= 50 * dt; });
    s.pops = s.pops.filter(function (p) { return p.life > 0; });
    s.clouds.forEach(function (cl) { cl.x -= move * 0.15; if (cl.x < -60) { cl.x = W + 60; cl.y = api.rand(30, 100); } });

    api.hud("🥕 " + s.carrots + " carrots", score().toLocaleString() + " pts");
  }

  function draw() {
    var sky = ctx.createLinearGradient(0, 0, 0, H);
    sky.addColorStop(0, "#d9efff");
    sky.addColorStop(1, "#fff3ea");
    ctx.fillStyle = sky;
    ctx.fillRect(0, 0, W, H);

    ctx.fillStyle = "rgba(255,255,255,0.9)";
    s.clouds.forEach(function (cl) {
      ctx.beginPath();
      ctx.arc(cl.x, cl.y, cl.r, 0, Math.PI * 2);
      ctx.arc(cl.x + cl.r, cl.y + 4, cl.r * 0.8, 0, Math.PI * 2);
      ctx.arc(cl.x - cl.r, cl.y + 6, cl.r * 0.7, 0, Math.PI * 2);
      ctx.fill();
    });

    // Distant office building with windows (slow parallax).
    var off = -(s.dist * 0.08) % 180;
    for (var b = -1; b < 6; b++) {
      var bx = off + b * 180;
      api.roundRect(ctx, bx + 10, 120, 120, GROUND - 120, 10, "#f2dcd0");
      ctx.fillStyle = "#cde8ff";
      for (var wy = 136; wy < GROUND - 30; wy += 30) {
        for (var wx = bx + 24; wx < bx + 120; wx += 32) ctx.fillRect(wx, wy, 18, 16);
      }
    }

    // Ground with moving tiles.
    ctx.fillStyle = "#f6d7c3";
    ctx.fillRect(0, GROUND, W, H - GROUND);
    ctx.fillStyle = "rgba(92,61,46,0.12)";
    var tile = -(s.dist % 48);
    for (var x = tile; x < W; x += 48) ctx.fillRect(x, GROUND, 2, H - GROUND);
    ctx.fillStyle = "#e8bfa6";
    ctx.fillRect(0, GROUND, W, 4);

    s.carrotsOnScreen.forEach(function (k) {
      ctx.fillStyle = "rgba(255,255,255,0.85)";
      ctx.beginPath();
      ctx.arc(k.x, k.y + Math.sin(k.bob) * 4, 19, 0, Math.PI * 2);
      ctx.fill();
      ctx.strokeStyle = "#ff9f40";
      ctx.lineWidth = 3;
      ctx.stroke();
      api.emoji(ctx, "🥕", k.x, k.y + Math.sin(k.bob) * 4, 24, 0.4);
    });

    s.obstacles.forEach(function (o) {
      var n = o.kind.stack || 1;
      for (var i = 0; i < n; i++) api.emoji(ctx, o.kind.emoji, o.x, GROUND - o.kind.size / 2 + 2 - i * 36, o.kind.size, 0);
    });

    // Shadow shrinks while airborne.
    var air = Math.min(1, (GROUND - s.y) / 140);
    ctx.fillStyle = "rgba(92,61,46," + (0.2 - air * 0.12) + ")";
    ctx.beginPath();
    ctx.ellipse(BUNNY_X, GROUND + 4, 24 - air * 10, 6 - air * 2, 0, 0, Math.PI * 2);
    ctx.fill();

    var squash = s.y >= GROUND && api.isPlaying() ? Math.sin(s.t * 18) * 0.04 : 0;
    ctx.save();
    ctx.translate(BUNNY_X, s.y - 30);
    ctx.scale(1 + squash, 1 - squash);
    api.image(ctx, "bunny", 0, 0, 66, Math.max(-0.35, Math.min(0.35, s.vy / 2200)));
    ctx.restore();

    s.pops.forEach(function (p) {
      ctx.globalAlpha = Math.max(0, p.life / 0.8);
      ctx.fillStyle = "#c2410c";
      ctx.font = "700 18px Fredoka, Quicksand, sans-serif";
      ctx.textAlign = "center";
      ctx.fillText("+10", p.x, p.y);
      ctx.globalAlpha = 1;
    });
  }

  function frame(now) {
    if (!api.isPlaying()) return;
    var dt = Math.min(0.04, (now - last) / 1000 || 0);
    last = now;
    update(dt);
    if (!api.isPlaying()) return;
    draw();
    raf = requestAnimationFrame(frame);
  }

  document.addEventListener("keydown", function (e) {
    if (e.key === " " || e.key === "ArrowUp" || e.key === "w" || e.key === "W") jump();
  });
  c.canvas.addEventListener("pointerdown", function (e) { if (api.isPlaying()) { e.preventDefault(); jump(); } });

  function run() {
    cancelAnimationFrame(raf);
    last = performance.now();
    raf = requestAnimationFrame(frame);
  }

  return {
    idle: function () { reset(); draw(); },
    start: function () { reset(); api.hud("🥕 0 carrots", "0 pts"); run(); },
    pause: function () { cancelAnimationFrame(raf); },
    resume: run
  };
});
