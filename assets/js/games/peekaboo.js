/* Peekaboo Pals — boop animals popping out of desk drawers. Avoid rain clouds. */
MeowArcade.register("peekaboo", function (api) {
  "use strict";

  var DURATION = 30;
  var board = document.createElement("div");
  board.className = "peek-board";
  api.stage.classList.add("stage-dom", "stage-peek");
  api.stage.appendChild(board);

  var holes = [];
  for (var i = 0; i < 9; i++) {
    var hole = document.createElement("div");
    hole.className = "peek-hole";
    var pop = document.createElement("button");
    pop.type = "button";
    pop.className = "peek-pop";
    pop.tabIndex = -1;
    pop.setAttribute("aria-label", "Empty drawer");
    var drawer = document.createElement("span");
    drawer.className = "peek-drawer";
    drawer.innerHTML = "<i></i>";
    hole.appendChild(pop);
    hole.appendChild(drawer);
    board.appendChild(hole);
    holes.push({ el: hole, pop: pop, up: false, until: 0, kind: "" });
    (function (h) {
      pop.addEventListener("pointerdown", function (e) { e.preventDefault(); boop(h); });
      pop.addEventListener("keydown", function (e) { if (e.key === "Enter") boop(h); });
    })(holes[i]);
  }

  var s, raf = 0, last = 0;

  function reset() {
    s = { t: 0, score: 0, combo: 0, nextSpawn: 0.5, boops: 0 };
    holes.forEach(hide);
  }

  function show(h, kind) {
    h.up = true;
    h.kind = kind;
    h.until = s.t + Math.max(0.7, 1.35 - s.t * 0.02);
    // Animal SVG is server-built from whitelisted keys; the cloud is a fixed emoji.
    h.pop.innerHTML = kind === "cloud" ? '<span class="peek-cloud">🌧️</span>' : api.svg(kind);
    h.pop.setAttribute("aria-label", kind === "cloud" ? "Rain cloud, don't boop" : "Boop " + kind);
    h.pop.tabIndex = 0;
    h.el.classList.remove("booped", "oops");
    h.el.classList.add("up");
  }

  function hide(h) {
    h.up = false;
    h.pop.tabIndex = -1;
    h.pop.setAttribute("aria-label", "Empty drawer");
    h.el.classList.remove("up");
  }

  function floatText(h, text, bad) {
    var f = document.createElement("span");
    f.className = "peek-float" + (bad ? " bad" : "");
    f.textContent = text;
    h.el.appendChild(f);
    setTimeout(function () { f.remove(); }, 800);
  }

  function boop(h) {
    if (!api.isPlaying() || !h.up) return;
    if (h.kind === "cloud") {
      s.score = Math.max(0, s.score - 15);
      s.combo = 0;
      h.el.classList.add("oops");
      floatText(h, "-15", true);
    } else {
      s.combo = Math.min(10, s.combo + 1);
      var pts = 10 + s.combo * 2;
      s.score += pts;
      s.boops++;
      h.el.classList.add("booped");
      floatText(h, "+" + pts, false);
    }
    hide(h);
    hud();
  }

  function hud() {
    api.hud("⏱ " + Math.max(0, Math.ceil(DURATION - s.t)) + "s · combo ×" + s.combo, s.score.toLocaleString() + " pts");
  }

  function update(dt) {
    s.t += dt;
    if (s.t >= DURATION) {
      holes.forEach(hide);
      api.over(s.score, s.boops + " friends booped");
      return;
    }
    holes.forEach(function (h) {
      if (h.up && s.t >= h.until) {
        if (h.kind !== "cloud") s.combo = 0; // a friend got away
        hide(h);
      }
    });
    s.nextSpawn -= dt;
    if (s.nextSpawn <= 0) {
      var free = holes.filter(function (h) { return !h.up; });
      if (free.length) {
        var cloudChance = Math.min(0.28, 0.12 + s.t * 0.006);
        show(api.pick(free), Math.random() < cloudChance ? "cloud" : api.pick(api.animals));
      }
      s.nextSpawn = Math.max(0.32, 0.85 - s.t * 0.018) * api.rand(0.7, 1.2);
    }
    hud();
  }

  function frame(now) {
    if (!api.isPlaying()) return;
    var dt = Math.min(0.05, (now - last) / 1000 || 0);
    last = now;
    update(dt);
    if (api.isPlaying()) raf = requestAnimationFrame(frame);
  }

  function run() {
    cancelAnimationFrame(raf);
    last = performance.now();
    raf = requestAnimationFrame(frame);
  }

  return {
    idle: function () {
      reset();
      // Show a few friends peeking so the board looks alive before play.
      show(holes[1], "hamster");
      show(holes[3], "frog");
      show(holes[8], "cloud");
    },
    start: function () { reset(); hud(); run(); },
    pause: function () { cancelAnimationFrame(raf); },
    resume: run
  };
});
