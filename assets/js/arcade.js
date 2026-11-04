/*
 * MeowOffice Arcade shell. Each game lives in assets/js/games/<key>.js and calls
 *
 *   MeowArcade.register("<key>", function (api) {
 *     return { idle(), start(), pause(), resume() };
 *   });
 *
 * The shell owns the overlay (play / pause / results), the HUD, avatar images,
 * score submission (game-score.php) and the leaderboard. Games only call
 * api.hud(left, right) while playing and api.over(score, note) when done.
 */
(function () {
  "use strict";

  var root = document.getElementById("arcade");
  var dataEl = document.getElementById("arcade-data");
  if (!root || !dataEl) return;

  var data = JSON.parse(dataEl.textContent);
  var key = root.dataset.game;
  var stage = document.getElementById("arcade-stage");
  var overlay = document.getElementById("arcade-overlay");
  var message = document.getElementById("arcade-message");
  var how = document.getElementById("arcade-how");
  var startBtn = document.getElementById("arcade-start");
  var hudLeft = document.getElementById("hud-left");
  var hudRight = document.getElementById("hud-right");
  var bestEl = document.getElementById("arcade-best");
  var board = document.getElementById("arcade-board");

  var EMOJI_FONT = '"Apple Color Emoji","Segoe UI Emoji","Noto Color Emoji",sans-serif';
  var images = {};
  var game = null;
  var state = "loading"; // loading | idle | playing | paused | over

  function loadImage(svg) {
    return new Promise(function (resolve) {
      var img = new Image();
      img.onload = function () { resolve(img); };
      img.onerror = function () { resolve(null); };
      img.src = "data:image/svg+xml;charset=utf-8," + encodeURIComponent(svg);
    });
  }

  var loading = Promise.all(Object.keys(data.avatars).map(function (k) {
    return loadImage(data.avatars[k]).then(function (img) { images[k] = img; });
  }));

  // ---- API handed to games -----------------------------------------------
  var api = {
    stage: stage,
    images: images,
    animals: data.animals,

    /** Server-built SVG markup for DOM games (whitelisted keys only). */
    svg: function (k) { return data.avatars[k] || ""; },

    /** Hi-DPI canvas sized to a logical W x H, filling the stage width. */
    canvas: function (w, h) {
      var canvas = document.createElement("canvas");
      var dpr = Math.min(window.devicePixelRatio || 1, 2);
      canvas.width = w * dpr;
      canvas.height = h * dpr;
      canvas.className = "arcade-canvas";
      canvas.style.aspectRatio = w + " / " + h;
      canvas.tabIndex = 0;
      stage.appendChild(canvas);
      var ctx = canvas.getContext("2d");
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      return {
        canvas: canvas,
        ctx: ctx,
        W: w,
        H: h,
        /** Convert a pointer position to canvas coordinates. */
        local: function (clientX, clientY) {
          var r = canvas.getBoundingClientRect();
          return { x: (clientX - r.left) / r.width * w, y: (clientY - r.top) / r.height * h };
        }
      };
    },

    isPlaying: function () { return state === "playing"; },

    hud: function (left, right) {
      hudLeft.textContent = left == null ? "" : String(left);
      hudRight.textContent = right == null ? "" : String(right);
    },

    over: function (score, note) { finish(Math.max(0, Math.floor(score)), note || ""); },

    // Small drawing helpers shared by canvas games.
    emoji: function (ctx, ch, x, y, size, angle) {
      ctx.save();
      ctx.translate(x, y);
      if (angle) ctx.rotate(angle);
      ctx.font = size + "px " + EMOJI_FONT;
      ctx.textAlign = "center";
      ctx.textBaseline = "middle";
      ctx.fillText(ch, 0, 0);
      ctx.restore();
    },
    image: function (ctx, k, x, y, size, angle) {
      var img = images[k];
      if (!img) return;
      ctx.save();
      ctx.translate(x, y);
      if (angle) ctx.rotate(angle);
      ctx.drawImage(img, -size / 2, -size / 2, size, size);
      ctx.restore();
    },
    roundRect: function (ctx, x, y, w, h, r, fill) {
      r = Math.min(r, w / 2, h / 2);
      ctx.beginPath();
      ctx.moveTo(x + r, y);
      ctx.arcTo(x + w, y, x + w, y + h, r);
      ctx.arcTo(x + w, y + h, x, y + h, r);
      ctx.arcTo(x, y + h, x, y, r);
      ctx.arcTo(x, y, x + w, y, r);
      ctx.closePath();
      if (fill) {
        ctx.fillStyle = fill;
        ctx.fill();
      }
    },
    rand: function (a, b) { return a + Math.random() * (b - a); },
    pick: function (list) { return list[Math.floor(Math.random() * list.length)]; },
    shuffle: function (list) {
      var a = list.slice();
      for (var i = a.length - 1; i > 0; i--) {
        var j = Math.floor(Math.random() * (i + 1));
        var t = a[i]; a[i] = a[j]; a[j] = t;
      }
      return a;
    }
  };

  // ---- Flow --------------------------------------------------------------
  function showOverlay(text, sub, button) {
    message.textContent = text;
    how.textContent = sub || "";
    startBtn.textContent = button;
    overlay.hidden = false;
    startBtn.focus({ preventScroll: true });
  }

  function start() {
    if (!game) return;
    if (state === "paused") {
      state = "playing";
      overlay.hidden = true;
      game.resume();
      return;
    }
    state = "playing";
    overlay.hidden = true;
    api.hud("", "");
    game.start();
  }

  function pause() {
    if (state !== "playing") return;
    state = "paused";
    game.pause();
    showOverlay("Paused. The office animals will wait for you.", "", "Resume");
  }

  function finish(score, note) {
    if (state !== "playing") return;
    state = "over";
    game.pause();
    showOverlay("Game over! Score: " + score.toLocaleString() + (note ? " · " + note : ""), "Saving your score…", "Play again");
    startBtn.disabled = true;

    var body = new URLSearchParams();
    body.set("csrf", root.dataset.csrf);
    body.set("game", key);
    body.set("score", String(score));
    fetch(root.dataset.endpoint, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: body.toString(),
      credentials: "same-origin"
    }).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (d) {
        if (!res.ok) throw new Error(d.error || "Score not saved");
        return d;
      });
    }).then(function (d) {
      var sub = d.newBest ? "🎉 New personal best!" : "Your best is " + Number(d.best).toLocaleString() + ".";
      if (d.unlocked && d.unlocked.length) sub += " Achievement unlocked: " + d.unlocked.join(", ") + "!";
      how.textContent = sub;
      bestEl.textContent = Number(d.best).toLocaleString();
      if (d.leaderboard) renderBoard(d.leaderboard);
    }).catch(function (err) {
      how.textContent = "(" + err.message + ")";
    }).then(function () {
      startBtn.disabled = false;
    });
  }

  function renderBoard(rows) {
    board.textContent = "";
    rows.forEach(function (row) {
      var li = document.createElement("li");
      var name = document.createElement("span");
      name.className = "user-cell small";
      name.innerHTML = row.avatar; // server-built SVG from whitelisted keys
      name.appendChild(document.createTextNode(" " + row.name));
      var best = document.createElement("strong");
      best.textContent = Number(row.best).toLocaleString();
      li.appendChild(name);
      li.appendChild(best);
      board.appendChild(li);
    });
  }

  // ---- Input that applies to every game ------------------------------------
  startBtn.addEventListener("click", start);

  var GAME_KEYS = [" ", "ArrowUp", "ArrowDown", "ArrowLeft", "ArrowRight"];
  document.addEventListener("keydown", function (e) {
    if (state === "playing") {
      if (GAME_KEYS.indexOf(e.key) !== -1) e.preventDefault(); // no page scrolling mid-game
      if (e.key === "p" || e.key === "P" || e.key === "Escape") pause();
    }
  });

  document.addEventListener("visibilitychange", function () { if (document.hidden) pause(); });
  window.addEventListener("blur", pause);

  window.MeowArcade = {
    register: function (k, factory) {
      if (k !== key) return;
      loading.then(function () {
        game = factory(api);
        state = "idle";
        if (game.idle) game.idle();
        startBtn.disabled = false;
        startBtn.textContent = "Play";
      });
    }
  };
})();
