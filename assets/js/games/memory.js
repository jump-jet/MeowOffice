/* Memory Paws — flip cards to match 8 pairs of office animals. */
MeowArcade.register("memory", function (api) {
  "use strict";

  var PAIRS = 8;
  var grid = document.createElement("div");
  grid.className = "memory-grid";
  api.stage.classList.add("stage-dom", "stage-memory");
  api.stage.appendChild(grid);

  var s, timer = 0, tickAt = 0;

  function score() {
    var extraMoves = Math.max(0, s.moves - PAIRS);
    return Math.max(100, Math.round(2000 - extraMoves * 40 - Math.floor(s.elapsed) * 8));
  }

  function hud() {
    api.hud("⏱ " + Math.floor(s.elapsed) + "s · " + s.moves + " moves", s.matched + " / " + PAIRS + " pairs");
  }

  function deal() {
    s = { first: null, lock: false, moves: 0, matched: 0, elapsed: 0 };
    var picks = api.shuffle(api.animals).slice(0, PAIRS);
    var deck = api.shuffle(picks.concat(picks));
    grid.textContent = "";
    deck.forEach(function (animal) {
      var card = document.createElement("button");
      card.type = "button";
      card.className = "mem-card";
      card.dataset.animal = animal;
      card.setAttribute("aria-label", "Hidden card");
      // Card faces: back is a paw print, front is the animal (server-built SVG).
      card.innerHTML = '<span class="mem-inner"><span class="mem-back">🐾</span><span class="mem-front">' + api.svg(animal) + "</span></span>";
      card.addEventListener("click", function () { flip(card); });
      grid.appendChild(card);
    });
  }

  function flip(card) {
    if (!api.isPlaying() || s.lock || card.classList.contains("flipped")) return;
    card.classList.add("flipped");
    card.setAttribute("aria-label", card.dataset.animal);
    if (!s.first) {
      s.first = card;
      return;
    }
    var a = s.first, b = card;
    s.first = null;
    s.moves++;
    if (a.dataset.animal === b.dataset.animal) {
      a.classList.add("matched");
      b.classList.add("matched");
      s.matched++;
      hud();
      if (s.matched === PAIRS) {
        stopTimer();
        setTimeout(function () { api.over(score(), "all pairs in " + s.moves + " moves"); }, 450);
      }
    } else {
      s.lock = true;
      setTimeout(function () {
        a.classList.remove("flipped");
        b.classList.remove("flipped");
        a.setAttribute("aria-label", "Hidden card");
        b.setAttribute("aria-label", "Hidden card");
        s.lock = false;
      }, 750);
      hud();
    }
  }

  function startTimer() {
    stopTimer();
    tickAt = performance.now();
    timer = setInterval(function () {
      var now = performance.now();
      s.elapsed += (now - tickAt) / 1000;
      tickAt = now;
      hud();
    }, 200);
  }

  function stopTimer() { clearInterval(timer); }

  return {
    idle: function () { deal(); },
    start: function () {
      deal();
      // A quick peek at every card before the timer starts.
      s.lock = true;
      s.peeking = true;
      Array.prototype.forEach.call(grid.children, function (card) { card.classList.add("flipped"); });
      hud();
      setTimeout(function () {
        Array.prototype.forEach.call(grid.children, function (card) { card.classList.remove("flipped"); });
        s.lock = false;
        s.peeking = false;
        if (api.isPlaying()) startTimer();
      }, 1400);
    },
    pause: stopTimer,
    resume: function () { if (!s.peeking) startTimer(); }
  };
});
