/*
 * Paperwork Pounce — the MeowOffice home-page mini game.
 * Catch paperwork and fish, dodge coffee. Scores are posted to the
 * endpoint in the canvas' data-endpoint attribute (game-score.php).
 */
(function () {
  "use strict";

  var canvas = document.getElementById("pounce");
  if (!canvas || !canvas.getContext) return;

  var ctx = canvas.getContext("2d");
  var overlay = document.getElementById("pounce-overlay");
  var message = document.getElementById("pounce-message");
  var startBtn = document.getElementById("pounce-start");
  var bestEl = document.getElementById("pounce-best");
  var board = document.getElementById("pounce-board");

  var W = 720;
  var H = 400;
  var FLOOR = H - 46;
  var EMOJI_FONT = '"Apple Color Emoji","Segoe UI Emoji","Noto Color Emoji",sans-serif';
  var PLAYER = canvas.dataset.player || "🐱";

  var ITEMS = {
    // ring = bubble outline colour so items stand out on the pastel background
    paper: { emoji: "📄", points: 1, size: 32, ring: "#f48fb1" },
    clip: { emoji: "📎", points: 1, size: 30, ring: "#f48fb1" },
    fish: { emoji: "🐟", points: 5, size: 34, ring: "#2a9d8f" },
    coffee: { emoji: "☕", points: 0, size: 32, ring: "#e5486b", hazard: true },
    yarn: { emoji: "🧶", points: 0, size: 30, ring: "#9b6bd6", life: true }
  };

  var state = null;
  var keys = {};
  var lastTime = 0;
  var running = false;

  // ---- Setup -------------------------------------------------------------

  function resize() {
    var dpr = Math.min(window.devicePixelRatio || 1, 2);
    canvas.width = W * dpr;
    canvas.height = H * dpr;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    if (!running) drawScene();
  }

  function newState() {
    return {
      x: W / 2,
      targetX: null,
      score: 0,
      lives: 3,
      time: 0,
      spawnIn: 0.6,
      items: [],
      pops: [],
      hurt: 0,
      shake: 0
    };
  }

  function pickKind(s) {
    var coffeeWeight = Math.min(4.5, 1.6 + s.time * 0.05);
    var table = [
      ["paper", 6],
      ["clip", 3],
      ["fish", 1],
      ["coffee", coffeeWeight],
      ["yarn", s.lives < 3 ? 0.35 : 0]
    ];
    var total = 0;
    table.forEach(function (row) { total += row[1]; });
    var r = Math.random() * total;
    for (var i = 0; i < table.length; i++) {
      r -= table[i][1];
      if (r <= 0) return table[i][0];
    }
    return "paper";
  }

  function spawn(s) {
    var kind = pickKind(s);
    var speed = 140 + s.time * 4.5 + Math.random() * 70;
    s.items.push({
      kind: kind,
      x: 30 + Math.random() * (W - 60),
      y: -30,
      vy: kind === "fish" ? speed * 1.2 : speed,
      wobble: Math.random() * Math.PI * 2,
      spin: (Math.random() - 0.5) * 2
    });
  }

  function pop(s, x, y, text, color) {
    s.pops.push({ x: x, y: y, text: text, color: color, life: 0.9 });
  }

  // ---- Loop --------------------------------------------------------------

  function update(dt) {
    var s = state;
    s.time += dt;

    var speed = 430;
    if (keys.left) s.x -= speed * dt;
    if (keys.right) s.x += speed * dt;
    if (s.targetX !== null && !keys.left && !keys.right) {
      s.x += (s.targetX - s.x) * Math.min(1, dt * 14);
    }
    s.x = Math.max(36, Math.min(W - 36, s.x));

    s.spawnIn -= dt;
    if (s.spawnIn <= 0) {
      spawn(s);
      s.spawnIn = Math.max(0.26, 0.85 - s.time * 0.012) * (0.7 + Math.random() * 0.6);
    }

    for (var i = s.items.length - 1; i >= 0; i--) {
      var it = s.items[i];
      it.y += it.vy * dt;
      it.wobble += dt * 3;
      var def = ITEMS[it.kind];

      var caught = Math.abs(it.x - s.x) < 46 && it.y > FLOOR - 58 && it.y < FLOOR - 8;
      if (caught) {
        s.items.splice(i, 1);
        if (def.hazard) {
          if (s.hurt <= 0) {
            s.lives -= 1;
            s.hurt = 1.1;
            s.shake = 0.35;
            pop(s, it.x, it.y, "-1 ❤", "#e5486b");
          }
        } else if (def.life) {
          s.lives = Math.min(3, s.lives + 1);
          pop(s, it.x, it.y, "+1 ❤", "#e5486b");
        } else {
          s.score += def.points;
          pop(s, it.x, it.y, "+" + def.points, def.points > 1 ? "#2a9d8f" : "#5c3d2e");
        }
        continue;
      }
      if (it.y > H + 30) s.items.splice(i, 1);
    }

    for (var j = s.pops.length - 1; j >= 0; j--) {
      s.pops[j].life -= dt;
      s.pops[j].y -= 40 * dt;
      if (s.pops[j].life <= 0) s.pops.splice(j, 1);
    }

    s.hurt = Math.max(0, s.hurt - dt);
    s.shake = Math.max(0, s.shake - dt);

    if (s.lives <= 0) gameOver();
  }

  function frame(now) {
    if (!running) return;
    var dt = Math.min(0.05, (now - lastTime) / 1000 || 0);
    lastTime = now;
    update(dt);
    drawScene();
    if (running) requestAnimationFrame(frame);
  }

  // ---- Drawing -----------------------------------------------------------

  function drawBackground() {
    var g = ctx.createLinearGradient(0, 0, 0, H);
    g.addColorStop(0, "#fff3ea");
    g.addColorStop(0.6, "#ffe8f2");
    g.addColorStop(1, "#eaf7ff");
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, W, H);

    // Window with a sky and a sun.
    roundRect(560, 76, 120, 90, 14, "#cde8ff");
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

    // Shelf with tiny binders.
    roundRect(40, 90, 200, 10, 5, "#e2b89a");
    ["#f48fb1", "#b8f0d8", "#e4d4ff", "#ffd9c8", "#cde8ff"].forEach(function (c, i) {
      roundRect(52 + i * 34, 48, 24, 42, 4, c);
    });

    // Floor / desk.
    roundRect(0, FLOOR, W, H - FLOOR, 0, "#f6d7c3");
    ctx.fillStyle = "rgba(92, 61, 46, 0.08)";
    for (var x = 0; x < W; x += 48) ctx.fillRect(x, FLOOR, 2, H - FLOOR);
  }

  function roundRect(x, y, w, h, r, fill) {
    ctx.fillStyle = fill;
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
    ctx.fill();
  }

  function emoji(char, x, y, size, angle) {
    ctx.save();
    ctx.translate(x, y);
    if (angle) ctx.rotate(angle);
    ctx.font = size + "px " + EMOJI_FONT;
    ctx.textAlign = "center";
    ctx.textBaseline = "middle";
    ctx.fillText(char, 0, 0);
    ctx.restore();
  }

  // White bubble + coloured ring + soft shadow behind each falling item.
  function drawBubble(x, y, def) {
    var r = def.size * 0.78;

    // Faint motion trail above the item.
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

  function hexToRgba(hex, alpha) {
    var n = parseInt(hex.slice(1), 16);
    return "rgba(" + (n >> 16) + "," + ((n >> 8) & 255) + "," + (n & 255) + "," + alpha + ")";
  }

  function drawScene() {
    var s = state;
    ctx.save();
    if (s && s.shake > 0) {
      ctx.translate((Math.random() - 0.5) * 10, (Math.random() - 0.5) * 6);
    }
    drawBackground();

    if (s) {
      s.items.forEach(function (it) {
        var def = ITEMS[it.kind];
        var x = it.x + Math.sin(it.wobble) * 6;
        drawBubble(x, it.y, def);
        emoji(def.emoji, x, it.y, def.size, Math.sin(it.wobble) * 0.25 * it.spin);
      });

      // Player: the cat with an in-tray.
      var blink = s.hurt > 0 && Math.floor(s.hurt * 12) % 2 === 0;
      if (!blink) {
        roundRect(s.x - 44, FLOOR - 16, 88, 14, 7, "#ffffff");
        roundRect(s.x - 40, FLOOR - 12, 80, 6, 3, "#f48fb1");
        emoji(PLAYER, s.x, FLOOR - 42, 48, 0);
      }

      s.pops.forEach(function (p) {
        ctx.globalAlpha = Math.max(0, p.life / 0.9);
        ctx.fillStyle = p.color;
        ctx.font = "700 20px Fredoka, Quicksand, sans-serif";
        ctx.textAlign = "center";
        ctx.fillText(p.text, p.x, p.y);
        ctx.globalAlpha = 1;
      });

      // HUD
      roundRect(14, 14, 132, 40, 20, "rgba(255,255,255,0.85)");
      ctx.fillStyle = "#3b2a24";
      ctx.font = "700 20px Fredoka, Quicksand, sans-serif";
      ctx.textAlign = "left";
      ctx.textBaseline = "middle";
      ctx.fillText("📄 " + s.score, 30, 35);

      roundRect(W - 146, 14, 132, 40, 20, "rgba(255,255,255,0.85)");
      var hearts = "";
      for (var i = 0; i < 3; i++) hearts += i < s.lives ? "❤️" : "🤍";
      emoji(hearts, W - 80, 35, 22, 0);
    } else {
      emoji(PLAYER, W / 2, FLOOR - 42, 48, 0);
    }
    ctx.restore();
  }

  // ---- Game flow ---------------------------------------------------------

  function start() {
    state = newState();
    running = true;
    overlay.hidden = true;
    lastTime = performance.now();
    canvas.focus();
    requestAnimationFrame(frame);
  }

  function pause() {
    if (!running) return;
    running = false;
    keys = {};
    showOverlay("Paused. Your paperwork will wait.", "Resume", true);
  }

  function resume() {
    running = true;
    overlay.hidden = true;
    lastTime = performance.now();
    requestAnimationFrame(frame);
  }

  function showOverlay(text, button, resumable) {
    message.textContent = text;
    startBtn.textContent = button;
    startBtn.dataset.resume = resumable ? "1" : "";
    overlay.hidden = false;
  }

  function gameOver() {
    running = false;
    keys = {};
    var score = state.score;
    drawScene();
    showOverlay("Game over! You filed " + score + " " + (score === 1 ? "paper" : "papers") + ". Saving…", "Play again", false);
    startBtn.disabled = true;
    submitScore(score).then(function (data) {
      var text = "Game over! Score: " + score + ".";
      if (data.newBest) text += " New personal best! 🎉";
      if (data.unlocked && data.unlocked.length) text += " Achievement unlocked: " + data.unlocked.join(", ") + "!";
      message.textContent = text;
      if (bestEl && typeof data.best === "number") bestEl.textContent = data.best;
      if (data.leaderboard) renderBoard(data.leaderboard);
    }).catch(function (err) {
      message.textContent = "Game over! Score: " + score + ". (" + err.message + ")";
    }).then(function () {
      startBtn.disabled = false;
    });
  }

  function submitScore(score) {
    var body = new URLSearchParams();
    body.set("csrf", canvas.dataset.csrf || "");
    body.set("score", String(score));
    return fetch(canvas.dataset.endpoint, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: body.toString(),
      credentials: "same-origin"
    }).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (data) {
        if (!res.ok) throw new Error(data.error || "Score not saved");
        return data;
      });
    });
  }

  function renderBoard(rows) {
    if (!board) return;
    board.textContent = "";
    rows.forEach(function (row) {
      var li = document.createElement("li");
      var name = document.createElement("span");
      var best = document.createElement("strong");
      name.textContent = row.emoji + " " + row.name;
      best.textContent = row.best;
      li.appendChild(name);
      li.appendChild(best);
      board.appendChild(li);
    });
  }

  // ---- Input -------------------------------------------------------------

  startBtn.addEventListener("click", function () {
    if (startBtn.dataset.resume) resume();
    else start();
  });

  function keyName(e) {
    if (e.key === "ArrowLeft" || e.key === "a" || e.key === "A") return "left";
    if (e.key === "ArrowRight" || e.key === "d" || e.key === "D") return "right";
    return null;
  }

  document.addEventListener("keydown", function (e) {
    var k = keyName(e);
    if (running && k) {
      keys[k] = true;
      e.preventDefault();
    } else if (running && (e.key === "p" || e.key === "Escape")) {
      pause();
    }
  });

  document.addEventListener("keyup", function (e) {
    var k = keyName(e);
    if (k) keys[k] = false;
  });

  function pointerToX(clientX) {
    var rect = canvas.getBoundingClientRect();
    return ((clientX - rect.left) / rect.width) * W;
  }

  canvas.addEventListener("mousemove", function (e) {
    if (running) state.targetX = pointerToX(e.clientX);
  });

  canvas.addEventListener("touchstart", function (e) {
    if (running) {
      state.targetX = pointerToX(e.touches[0].clientX);
      e.preventDefault();
    }
  }, { passive: false });

  canvas.addEventListener("touchmove", function (e) {
    if (running) {
      state.targetX = pointerToX(e.touches[0].clientX);
      e.preventDefault();
    }
  }, { passive: false });

  document.addEventListener("visibilitychange", function () {
    if (document.hidden) pause();
  });
  window.addEventListener("blur", pause);
  window.addEventListener("resize", resize);

  canvas.tabIndex = 0;
  resize();
})();
