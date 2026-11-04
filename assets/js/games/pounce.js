/* Paperwork Pounce — catch paperwork and fish with your avatar, dodge spilled coffee. */
MeowArcade.register("pounce", function (api) {
  "use strict";

  var c = api.canvas(720, 400);
  var ctx = c.ctx, W = c.W, H = c.H;
  var FLOOR = H - 46;

  var ITEMS = {
    // ring = bubble outline colour so items stand out on the pastel background
    paper: { emoji: "📄", points: 1, size: 32, ring: "#f48fb1" },
    clip: { emoji: "📎", points: 1, size: 30, ring: "#f48fb1" },
    fish: { emoji: "🐟", points: 5, size: 34, ring: "#2a9d8f" },
    coffee: { emoji: "☕", points: 0, size: 32, ring: "#e5486b", hazard: true },
    yarn: { emoji: "🧶", points: 0, size: 30, ring: "#9b6bd6", life: true }
  };

  var s = null, keys = {}, raf = 0, last = 0;

  function reset() {
    s = { x: W / 2, targetX: null, score: 0, lives: 3, time: 0, spawnIn: 0.6, items: [], pops: [], hurt: 0, shake: 0 };
  }

  function pickKind() {
    var table = [
      ["paper", 6], ["clip", 3], ["fish", 1],
      ["coffee", Math.min(4.5, 1.6 + s.time * 0.05)],
      ["yarn", s.lives < 3 ? 0.35 : 0]
    ];
    var total = table.reduce(function (sum, row) { return sum + row[1]; }, 0);
    var r = Math.random() * total;
    for (var i = 0; i < table.length; i++) {
      r -= table[i][1];
      if (r <= 0) return table[i][0];
    }
    return "paper";
  }

  function spawn() {
    var kind = pickKind();
    var speed = 140 + s.time * 4.5 + Math.random() * 70;
    s.items.push({ kind: kind, x: 30 + Math.random() * (W - 60), y: -30, vy: kind === "fish" ? speed * 1.2 : speed, wobble: Math.random() * 6.3, spin: Math.random() - 0.5 });
  }

  function pop(x, y, text, color) { s.pops.push({ x: x, y: y, text: text, color: color, life: 0.9 }); }

  function hud() {
    var hearts = "";
    for (var i = 0; i < 3; i++) hearts += i < s.lives ? "❤️" : "🤍";
    api.hud(hearts, "📄 " + s.score + " filed");
  }

  function update(dt) {
    s.time += dt;
    if (keys.left) s.x -= 430 * dt;
    if (keys.right) s.x += 430 * dt;
    if (s.targetX !== null && !keys.left && !keys.right) s.x += (s.targetX - s.x) * Math.min(1, dt * 14);
    s.x = Math.max(36, Math.min(W - 36, s.x));

    s.spawnIn -= dt;
    if (s.spawnIn <= 0) {
      spawn();
      s.spawnIn = Math.max(0.26, 0.85 - s.time * 0.012) * (0.7 + Math.random() * 0.6);
    }

    for (var i = s.items.length - 1; i >= 0; i--) {
      var it = s.items[i];
      var def = ITEMS[it.kind];
      it.y += it.vy * dt;
      it.wobble += dt * 3;
      if (Math.abs(it.x - s.x) < 46 && it.y > FLOOR - 58 && it.y < FLOOR - 8) {
        s.items.splice(i, 1);
        if (def.hazard) {
          if (s.hurt <= 0) {
            s.lives--;
            s.hurt = 1.1;
            s.shake = 0.35;
            pop(it.x, it.y, "-1 ❤", "#e5486b");
          }
        } else if (def.life) {
          s.lives = Math.min(3, s.lives + 1);
          pop(it.x, it.y, "+1 ❤", "#e5486b");
        } else {
          s.score += def.points;
          pop(it.x, it.y, "+" + def.points, def.points > 1 ? "#2a9d8f" : "#5c3d2e");
        }
        hud();
        continue;
      }
      if (it.y > H + 30) s.items.splice(i, 1);
    }
    s.pops.forEach(function (p) { p.life -= dt; p.y -= 40 * dt; });
    s.pops = s.pops.filter(function (p) { return p.life > 0; });
    s.hurt = Math.max(0, s.hurt - dt);
    s.shake = Math.max(0, s.shake - dt);

    if (s.lives <= 0) {
      draw();
      api.over(s.score, s.score === 1 ? "1 paper filed" : s.score + " papers filed");
    }
  }

  function drawBackground() {
    var g = ctx.createLinearGradient(0, 0, 0, H);
    g.addColorStop(0, "#fff3ea");
    g.addColorStop(0.6, "#ffe8f2");
    g.addColorStop(1, "#eaf7ff");
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, W, H);

    api.roundRect(ctx, 560, 76, 120, 90, 14, "#cde8ff");
    ctx.fillStyle = "#ffe08a";
    ctx.beginPath();
    ctx.arc(650, 104, 16, 0, Math.PI * 2);
    ctx.fill();
    ctx.strokeStyle = "#ffffff";
    ctx.lineWidth = 6;
    ctx.strokeRect(560, 76, 120, 90);
    ctx.beginPath();
    ctx.moveTo(620, 76);
    ctx.lineTo(620, 166);
    ctx.stroke();

    api.roundRect(ctx, 40, 90, 200, 10, 5, "#e2b89a");
    ["#f48fb1", "#b8f0d8", "#e4d4ff", "#ffd9c8", "#cde8ff"].forEach(function (col, i) {
      api.roundRect(ctx, 52 + i * 34, 48, 24, 42, 4, col);
    });

    ctx.fillStyle = "#f6d7c3";
    ctx.fillRect(0, FLOOR, W, H - FLOOR);
    ctx.fillStyle = "rgba(92, 61, 46, 0.08)";
    for (var x = 0; x < W; x += 48) ctx.fillRect(x, FLOOR, 2, H - FLOOR);
  }

  function hexToRgba(hex, alpha) {
    var n = parseInt(hex.slice(1), 16);
    return "rgba(" + (n >> 16) + "," + ((n >> 8) & 255) + "," + (n & 255) + "," + alpha + ")";
  }

  // White bubble + coloured ring + soft shadow so items stand out.
  function drawBubble(x, y, def) {
    var r = def.size * 0.78;
    var trail = ctx.createLinearGradient(x, y - r * 3, x, y);
    trail.addColorStop(0, "rgba(255,255,255,0)");
    trail.addColorStop(1, hexToRgba(def.ring, 0.25));
    ctx.fillStyle = trail;
    ctx.fillRect(x - r * 0.45, y - r * 3, r * 0.9, r * 2.2);

    ctx.save();
    ctx.shadowColor = "rgba(92, 61, 46, 0.28)";
    ctx.shadowBlur = 10;
    ctx.shadowOffsetY = 4;
    ctx.fillStyle = "#ffffff";
    ctx.beginPath();
    ctx.arc(x, y, r, 0, Math.PI * 2);
    ctx.fill();
    ctx.restore();

    ctx.lineWidth = def.hazard ? 4 : 3;
    ctx.strokeStyle = def.ring;
    if (def.hazard) ctx.setLineDash([6, 4]);
    ctx.beginPath();
    ctx.arc(x, y, r, 0, Math.PI * 2);
    ctx.stroke();
    ctx.setLineDash([]);
  }

  function draw() {
    ctx.save();
    if (s && s.shake > 0) ctx.translate((Math.random() - 0.5) * 10, (Math.random() - 0.5) * 6);
    drawBackground();
    s.items.forEach(function (it) {
      var def = ITEMS[it.kind];
      var x = it.x + Math.sin(it.wobble) * 6;
      drawBubble(x, it.y, def);
      api.emoji(ctx, def.emoji, x, it.y, def.size, Math.sin(it.wobble) * 0.25 * it.spin);
    });
    var blink = s.hurt > 0 && Math.floor(s.hurt * 12) % 2 === 0;
    if (!blink) {
      api.roundRect(ctx, s.x - 44, FLOOR - 16, 88, 14, 7, "#ffffff");
      api.roundRect(ctx, s.x - 40, FLOOR - 12, 80, 6, 3, "#f48fb1");
      api.image(ctx, "player", s.x, FLOOR - 40, 66);
    }
    s.pops.forEach(function (p) {
      ctx.globalAlpha = Math.max(0, p.life / 0.9);
      ctx.fillStyle = p.color;
      ctx.font = "700 20px Fredoka, Quicksand, sans-serif";
      ctx.textAlign = "center";
      ctx.fillText(p.text, p.x, p.y);
      ctx.globalAlpha = 1;
    });
    ctx.restore();
  }

  function frame(now) {
    if (!api.isPlaying()) return;
    var dt = Math.min(0.05, (now - last) / 1000 || 0);
    last = now;
    update(dt);
    if (!api.isPlaying()) return;
    draw();
    raf = requestAnimationFrame(frame);
  }

  function run() {
    cancelAnimationFrame(raf);
    keys = {};
    last = performance.now();
    raf = requestAnimationFrame(frame);
  }

  // ---- Input ----
  function keyName(e) {
    if (e.key === "ArrowLeft" || e.key === "a" || e.key === "A") return "left";
    if (e.key === "ArrowRight" || e.key === "d" || e.key === "D") return "right";
    return null;
  }
  document.addEventListener("keydown", function (e) {
    var k = keyName(e);
    if (k && api.isPlaying()) { keys[k] = true; e.preventDefault(); }
  });
  document.addEventListener("keyup", function (e) {
    var k = keyName(e);
    if (k) keys[k] = false;
  });
  function follow(clientX) {
    if (api.isPlaying()) s.targetX = c.local(clientX, 0).x;
  }
  c.canvas.addEventListener("mousemove", function (e) { follow(e.clientX); });
  c.canvas.addEventListener("touchstart", function (e) { if (api.isPlaying()) { follow(e.touches[0].clientX); e.preventDefault(); } }, { passive: false });
  c.canvas.addEventListener("touchmove", function (e) { if (api.isPlaying()) { follow(e.touches[0].clientX); e.preventDefault(); } }, { passive: false });

  return {
    idle: function () { reset(); draw(); },
    start: function () { reset(); hud(); run(); },
    pause: function () { cancelAnimationFrame(raf); keys = {}; },
    resume: run
  };
});
