/* Counting Sheep — count only the sheep jumping the fence; decoys join later. */
MeowArcade.register("sheep", function (api) {
  "use strict";

  var c = api.canvas(720, 340);
  var ctx = c.ctx, W = c.W, H = c.H;
  var GROUND = 280;
  var FENCE_X = W / 2;
  var DECOYS = ["pig", "duck"];

  var choices = document.createElement("div");
  choices.className = "sheep-choices";
  choices.hidden = true;
  api.stage.appendChild(choices);
  api.stage.classList.add("stage-sheep");

  var stars = [];
  for (var i = 0; i < 40; i++) stars.push({ x: Math.random() * W, y: Math.random() * 170, r: api.rand(0.6, 1.8), tw: Math.random() * 6 });

  var s, raf = 0, last = 0;

  function reset() {
    s = { round: 1, lives: 3, score: 0, phase: "watch", jumpers: [], clock: 0, answer: 0, banner: "", bannerT: 0, t: 0 };
  }

  function setupRound() {
    var r = s.round;
    var sheep = Math.min(14, 3 + Math.floor(r * 1.3) + Math.floor(Math.random() * 2));
    var decoys = r < 3 ? 0 : Math.min(7, Math.floor((r - 2) * 1.2) + Math.floor(Math.random() * 2));
    var list = [];
    for (var a = 0; a < sheep; a++) list.push("sheep");
    for (var b = 0; b < decoys; b++) list.push(api.pick(DECOYS));
    list = api.shuffle(list);

    var at = 0.6;
    var gapMax = Math.max(0.35, 1.1 - r * 0.07);
    s.jumpers = list.map(function (kind) {
      var j = { kind: kind, start: at, speed: api.rand(200, 260) + r * 14, x: -50, done: false };
      at += api.rand(0.22, gapMax);
      return j;
    });
    s.answer = sheep;
    s.clock = 0;
    s.phase = "watch";
    s.banner = decoys ? "Round " + r + " · only count the sheep!" : "Round " + r + " · count the sheep";
    s.bannerT = 2.2;
    choices.hidden = true;
    hud();
  }

  function hud() {
    var hearts = "";
    for (var h = 0; h < 3; h++) hearts += h < s.lives ? "❤️" : "🤍";
    api.hud(hearts + "  round " + s.round, s.score.toLocaleString() + " pts");
  }

  function showChoices() {
    s.phase = "answer";
    var opts = [s.answer];
    while (opts.length < 4) {
      var n = s.answer + api.pick([-3, -2, -1, 1, 2, 3]);
      if (n >= 0 && opts.indexOf(n) === -1) opts.push(n);
    }
    opts.sort(function (a, b) { return a - b; });
    choices.textContent = "";
    var label = document.createElement("strong");
    label.textContent = "How many sheep jumped?";
    choices.appendChild(label);
    opts.forEach(function (n, i) {
      var btn = document.createElement("button");
      btn.type = "button";
      btn.innerHTML = "<kbd>" + (i + 1) + "</kbd> ";
      btn.appendChild(document.createTextNode(String(n)));
      btn.addEventListener("click", function () { answer(n); });
      choices.appendChild(btn);
    });
    choices.hidden = false;
    choices.querySelector("button").focus({ preventScroll: true });
  }

  function answer(n) {
    if (!api.isPlaying() || s.phase !== "answer") return;
    choices.hidden = true;
    s.phase = "result";
    if (n === s.answer) {
      var pts = 100 * s.round;
      s.score += pts;
      s.banner = "Correct! +" + pts + " 🎉";
      s.round++;
    } else {
      s.lives--;
      s.banner = "Oops, it was " + s.answer + " sheep";
    }
    s.bannerT = 1.4;
    hud();
    s.resultAt = s.t + 1.4;
  }

  function update(dt) {
    s.t += dt;
    s.bannerT = Math.max(0, s.bannerT - dt);
    if (s.phase === "watch") {
      s.clock += dt;
      var allDone = true;
      s.jumpers.forEach(function (j) {
        if (s.clock < j.start) { allDone = false; return; }
        if (j.done) return;
        j.x += j.speed * dt;
        if (j.x > W + 60) j.done = true; else allDone = false;
      });
      if (allDone) showChoices();
    } else if (s.phase === "result" && s.t >= s.resultAt) {
      if (s.lives <= 0) {
        api.over(s.score, "reached round " + s.round);
        return;
      }
      setupRound();
    }
  }

  function jumperY(x) {
    // A hop arc centred on the fence.
    var d = (x - FENCE_X) / 120;
    return GROUND - (Math.abs(d) < 1 ? (1 - d * d) * 95 : 0);
  }

  function draw() {
    var sky = ctx.createLinearGradient(0, 0, 0, H);
    sky.addColorStop(0, "#3d3a78");
    sky.addColorStop(0.7, "#8f7cc9");
    sky.addColorStop(1, "#f4c6dd");
    ctx.fillStyle = sky;
    ctx.fillRect(0, 0, W, H);

    stars.forEach(function (st) {
      ctx.globalAlpha = 0.5 + Math.sin((s ? s.t : 0) * 2 + st.tw) * 0.4;
      ctx.fillStyle = "#fff";
      ctx.beginPath();
      ctx.arc(st.x, st.y, st.r, 0, Math.PI * 2);
      ctx.fill();
    });
    ctx.globalAlpha = 1;

    // Glowing full moon with a few craters.
    var glow = ctx.createRadialGradient(620, 70, 20, 620, 70, 80);
    glow.addColorStop(0, "rgba(255,244,194,0.55)");
    glow.addColorStop(1, "rgba(255,244,194,0)");
    ctx.fillStyle = glow;
    ctx.fillRect(540, -10, 160, 160);
    ctx.fillStyle = "#fff4c2";
    ctx.beginPath();
    ctx.arc(620, 70, 30, 0, Math.PI * 2);
    ctx.fill();
    ctx.fillStyle = "rgba(214,196,140,0.45)";
    [[610, 62, 6], [632, 78, 4.5], [622, 88, 3]].forEach(function (m) {
      ctx.beginPath();
      ctx.arc(m[0], m[1], m[2], 0, Math.PI * 2);
      ctx.fill();
    });

    // Rolling hill
    ctx.fillStyle = "#9fdc9a";
    ctx.beginPath();
    ctx.moveTo(0, GROUND - 6);
    ctx.quadraticCurveTo(W / 2, GROUND - 40, W, GROUND - 6);
    ctx.lineTo(W, H);
    ctx.lineTo(0, H);
    ctx.fill();
    ctx.fillStyle = "#7cc77a";
    ctx.fillRect(0, GROUND + 10, W, H - GROUND - 10);

    // Fence
    ctx.fillStyle = "#c98b5a";
    ctx.strokeStyle = "#7a4b2e";
    ctx.lineWidth = 2;
    [-34, 0, 34].forEach(function (dx) {
      api.roundRect(ctx, FENCE_X + dx - 6, GROUND - 60, 12, 66, 4, "#c98b5a");
      ctx.stroke();
    });
    api.roundRect(ctx, FENCE_X - 52, GROUND - 48, 104, 10, 4, "#d9a070"); ctx.stroke();
    api.roundRect(ctx, FENCE_X - 52, GROUND - 24, 104, 10, 4, "#d9a070"); ctx.stroke();

    if (s) {
      s.jumpers.forEach(function (j) {
        if (s.clock < j.start || j.done) return;
        var y = jumperY(j.x);
        var tilt = (j.x - FENCE_X) / 600;
        ctx.fillStyle = "rgba(40,30,60,0.25)";
        ctx.beginPath();
        ctx.ellipse(j.x, GROUND + 4, 20, 5, 0, 0, Math.PI * 2);
        ctx.fill();
        api.image(ctx, j.kind, j.x, y - 30, 62, tilt);
      });

      if (s.bannerT > 0) {
        ctx.globalAlpha = Math.min(1, s.bannerT * 2);
        api.roundRect(ctx, W / 2 - 200, 22, 400, 44, 22, "rgba(255,255,255,0.9)");
        ctx.fillStyle = "#3b2a24";
        ctx.font = "700 19px Fredoka, Quicksand, sans-serif";
        ctx.textAlign = "center";
        ctx.textBaseline = "middle";
        ctx.fillText(s.banner, W / 2, 44);
        ctx.textBaseline = "alphabetic";
        ctx.globalAlpha = 1;
      }
    } else {
      api.image(ctx, "sheep", FENCE_X, GROUND - 120, 70, 0);
    }
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
    last = performance.now();
    raf = requestAnimationFrame(frame);
  }

  document.addEventListener("keydown", function (e) {
    if (!api.isPlaying() || s.phase !== "answer") return;
    var n = parseInt(e.key, 10);
    var btns = choices.querySelectorAll("button");
    if (n >= 1 && n <= btns.length) btns[n - 1].click();
  });

  return {
    idle: function () { s = null; draw(); },
    start: function () { reset(); setupRound(); run(); },
    pause: function () { cancelAnimationFrame(raf); },
    resume: run
  };
});
