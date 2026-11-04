/* Pancake Panda — drop swinging pancakes to build the tallest stack. */
MeowArcade.register("stack", function (api) {
  "use strict";

  var c = api.canvas(720, 440);
  var ctx = c.ctx, W = c.W, H = c.H;
  var LAYER = 22;
  var BASE_Y = H - 70;          // top of the plate, in world coordinates
  var START_W = 220;
  var PERFECT = 5;              // px of tolerance for a perfect drop

  var s, raf = 0, last = 0;

  function reset() {
    s = {
      layers: [{ x: W / 2 - START_W / 2, w: START_W }],
      cur: null, dir: 1, speed: 220, cam: 0, camTarget: 0,
      perfects: 0, streak: 0, debris: [], flash: 0, t: 0
    };
    nextPancake();
  }

  function topLayer() { return s.layers[s.layers.length - 1]; }
  function height() { return s.layers.length - 1; }
  function score() { return height() * 10 + s.perfects * 5; }

  function nextPancake() {
    var top = topLayer();
    s.dir = Math.random() < 0.5 ? 1 : -1;
    // Start at the edge of the swing (partly on screen) so an instant tap isn't an automatic miss.
    s.cur = { x: s.dir > 0 ? -top.w * 0.6 : W - top.w * 0.4, w: top.w };
    s.speed = Math.min(620, 220 + height() * 14);
  }

  function layerY(i) { return BASE_Y - (i + 1) * LAYER; }

  function drop() {
    if (!api.isPlaying()) return;
    var top = topLayer(), cur = s.cur;
    var left = Math.max(cur.x, top.x), right = Math.min(cur.x + cur.w, top.x + top.w);
    var overlap = right - left;
    var y = layerY(s.layers.length);

    if (overlap <= 0) {
      s.debris.push({ x: cur.x, y: y, w: cur.w, vy: 0, vx: s.dir * 60, rot: 0 });
      s.cur = null;
      draw();
      api.over(score(), "stack of " + height());
      return;
    }

    if (Math.abs(cur.x - top.x) <= PERFECT) {
      s.perfects++;
      s.streak++;
      s.flash = 0.5;
      var grow = s.streak % 3 === 0 ? 12 : 0; // every 3 perfects in a row, the pancake grows back a bit
      var w = Math.min(START_W, top.w + grow);
      s.layers.push({ x: top.x - (w - top.w) / 2, w: w, perfect: true });
    } else {
      s.streak = 0;
      // The overhang falls off.
      var cutX = cur.x < top.x ? cur.x : right;
      s.debris.push({ x: cutX, y: y, w: cur.w - overlap, vy: 0, vx: (cur.x < top.x ? -1 : 1) * 80, rot: 0 });
      s.layers.push({ x: left, w: overlap });
    }
    s.camTarget = Math.max(0, (s.layers.length - 9) * LAYER);
    nextPancake();
    api.hud("🥞 " + height() + " high · perfect ×" + s.streak, score().toLocaleString() + " pts");
  }

  function update(dt) {
    s.t += dt;
    if (s.cur) {
      s.cur.x += s.dir * s.speed * dt;
      var minX = -s.cur.w * 0.6, maxX = W - s.cur.w * 0.4;
      if (s.cur.x > maxX) { s.cur.x = maxX; s.dir = -1; }
      if (s.cur.x < minX) { s.cur.x = minX; s.dir = 1; }
    }
    s.cam += (s.camTarget - s.cam) * Math.min(1, dt * 6);
    s.debris.forEach(function (d) { d.vy += 1400 * dt; d.y += d.vy * dt; d.x += d.vx * dt; d.rot += d.vx * 0.0006; });
    s.debris = s.debris.filter(function (d) { return d.y - s.cam < H + 80; });
    s.flash = Math.max(0, s.flash - dt);
  }

  function pancake(x, y, w, perfect) {
    api.roundRect(ctx, x, y, w, LAYER - 2, 10, "#f2b866");
    api.roundRect(ctx, x + 3, y + 2, w - 6, 7, 5, "#f8d398");
    ctx.strokeStyle = "#b9762e";
    ctx.lineWidth = 2;
    api.roundRect(ctx, x, y, w, LAYER - 2, 10);
    ctx.stroke();
    if (perfect) {
      ctx.fillStyle = "rgba(255,255,255,0.7)";
      ctx.beginPath();
      ctx.arc(x + w - 14, y + 6, 2.5, 0, Math.PI * 2);
      ctx.fill();
    }
  }

  function draw() {
    var bg = ctx.createLinearGradient(0, 0, 0, H);
    bg.addColorStop(0, "#fff6e6");
    bg.addColorStop(1, "#ffe3ec");
    ctx.fillStyle = bg;
    ctx.fillRect(0, 0, W, H);

    // Kitchen tiles drift with the camera for a sense of height.
    ctx.fillStyle = "rgba(92,61,46,0.05)";
    var off = s.cam % 40;
    for (var ty = -40 + off; ty < H; ty += 40) ctx.fillRect(0, ty, W, 1);

    ctx.save();
    ctx.translate(0, s.cam);

    // Plate
    ctx.fillStyle = "#ffffff";
    ctx.beginPath();
    ctx.ellipse(W / 2, BASE_Y + 8, START_W / 2 + 46, 16, 0, 0, Math.PI * 2);
    ctx.fill();
    ctx.strokeStyle = "#d9cbc2";
    ctx.lineWidth = 2;
    ctx.stroke();

    s.layers.forEach(function (l, i) {
      if (i === 0) return; // index 0 is the invisible footprint on the plate
      pancake(l.x, layerY(i), l.w, l.perfect);
    });

    var top = topLayer();
    if (s.layers.length > 1) {
      // Butter on top + a syrup drip.
      var ty2 = layerY(s.layers.length - 1);
      api.roundRect(ctx, top.x + top.w / 2 - 14, ty2 - 8, 28, 10, 3, "#fff1a8");
      ctx.fillStyle = "rgba(170,90,20,0.55)";
      api.roundRect(ctx, top.x + 10, ty2 + 2, 8, 18, 4, "rgba(170,90,20,0.55)");
    }

    if (s.cur) pancake(s.cur.x, layerY(s.layers.length), s.cur.w, false);

    s.debris.forEach(function (d) {
      ctx.save();
      ctx.translate(d.x + d.w / 2, d.y + LAYER / 2);
      ctx.rotate(d.rot);
      pancake(-d.w / 2, -LAYER / 2, d.w, false);
      ctx.restore();
    });
    ctx.restore();

    // Bao the Panda watches from the corner (screen space, always visible).
    var bob = Math.sin(s.t * 3) * 3;
    api.image(ctx, "panda", 78, H - 70 + bob, 110, s.flash > 0 ? Math.sin(s.flash * 30) * 0.15 : 0);
    if (s.flash > 0) {
      ctx.globalAlpha = Math.min(1, s.flash * 3);
      ctx.fillStyle = "#3b2a24";
      ctx.font = "700 22px Fredoka, Quicksand, sans-serif";
      ctx.textAlign = "center";
      ctx.fillText("Perfect!", W / 2, 60);
      ctx.globalAlpha = 1;
    }

    ctx.fillStyle = "rgba(255,255,255,0.85)";
    api.roundRect(ctx, W - 120, 16, 104, 44, 22, "rgba(255,255,255,0.85)");
    ctx.fillStyle = "#3b2a24";
    ctx.font = "700 22px Fredoka, Quicksand, sans-serif";
    ctx.textAlign = "center";
    ctx.textBaseline = "middle";
    ctx.fillText("🥞 " + height(), W - 68, 39);
    ctx.textBaseline = "alphabetic";
  }

  function frame(now) {
    if (!api.isPlaying()) return;
    var dt = Math.min(0.04, (now - last) / 1000 || 0);
    last = now;
    update(dt);
    draw();
    raf = requestAnimationFrame(frame);
  }

  function run() {
    cancelAnimationFrame(raf);
    last = performance.now();
    raf = requestAnimationFrame(frame);
  }

  document.addEventListener("keydown", function (e) {
    if ((e.key === " " || e.key === "ArrowDown" || e.key === "Enter") && api.isPlaying() && !e.repeat) {
      e.preventDefault();
      drop();
    }
  });
  c.canvas.addEventListener("pointerdown", function (e) { if (api.isPlaying()) { e.preventDefault(); drop(); } });

  return {
    idle: function () { reset(); draw(); },
    start: function () { reset(); api.hud("🥞 0 high · perfect ×0", "0 pts"); run(); },
    pause: function () { cancelAnimationFrame(raf); },
    resume: run
  };
});
