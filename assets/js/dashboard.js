/*
 * Dashboard behaviour:
 *  - chart tooltips (+ crosshair on line charts) for any element with .hit[data-tip]
 *  - Live Activity Feed polling (#feed[data-feed])
 *  - System Health auto-refresh (#health[data-refresh])
 * Everything pauses while the tab is hidden.
 */
(function () {
  "use strict";

  // ---- Chart tooltips -------------------------------------------------------
  var tip = document.getElementById("chart-tip");
  if (!tip) {
    tip = document.createElement("div");
    tip.className = "chart-tip";
    tip.hidden = true;
    document.body.appendChild(tip);
  }

  function showTip(el, x, y) {
    tip.textContent = el.getAttribute("data-tip");
    tip.hidden = false;
    var pad = 12;
    var w = tip.offsetWidth;
    var left = Math.min(window.innerWidth - w - 8, x + pad);
    tip.style.left = Math.max(8, left) + "px";
    tip.style.top = (y - tip.offsetHeight - pad) + "px";
    el.classList.add("hit-on");
    var svg = el.ownerSVGElement;
    var cross = svg && svg.querySelector(".crosshair");
    if (cross && el.hasAttribute("data-x")) {
      cross.setAttribute("x1", el.getAttribute("data-x"));
      cross.setAttribute("x2", el.getAttribute("data-x"));
      cross.classList.add("on");
    }
  }

  function hideTip(el) {
    tip.hidden = true;
    el.classList.remove("hit-on");
    var svg = el.ownerSVGElement;
    var cross = svg && svg.querySelector(".crosshair");
    if (cross) cross.classList.remove("on");
  }

  document.querySelectorAll(".hit[data-tip]").forEach(function (el) {
    el.addEventListener("mousemove", function (e) { showTip(el, e.clientX, e.clientY); });
    el.addEventListener("mouseleave", function () { hideTip(el); });
    el.addEventListener("focus", function () {
      var r = el.getBoundingClientRect();
      showTip(el, r.left + r.width / 2, r.top);
    });
    el.addEventListener("blur", function () { hideTip(el); });
    // The <title> fallback would double up with our tooltip.
    var t = el.querySelector("title");
    if (t) t.remove();
  });

  // ---- Live Activity Feed ---------------------------------------------------
  var feed = document.getElementById("feed");
  var status = document.getElementById("feed-status");
  var clockOffset = 0; // server seconds - browser seconds

  function ago(ts) {
    var diff = Math.max(0, Math.round(Date.now() / 1000 + clockOffset - ts));
    if (diff < 45) return "just now";
    if (diff < 3600) return Math.round(diff / 60) + "m ago";
    if (diff < 86400) return Math.round(diff / 3600) + "h ago";
    return Math.round(diff / 86400) + "d ago";
  }

  function refreshAgo() {
    document.querySelectorAll("time[data-ts]").forEach(function (t) {
      t.textContent = ago(Number(t.getAttribute("data-ts")));
    });
  }

  function feedItem(item) {
    var li = document.createElement("li");
    li.className = "feed-item feed-new";

    // Avatar markup is server-generated SVG built from whitelisted keys only.
    var avatar = document.createElement("span");
    avatar.innerHTML = item.avatar;
    li.appendChild(avatar.firstElementChild || avatar);

    var body = document.createElement("div");
    var who = document.createElement("strong");
    who.textContent = item.who;
    var icon = document.createElement("span");
    icon.className = "feed-icon";
    icon.textContent = item.icon;
    body.appendChild(who);
    body.appendChild(document.createTextNode(" "));
    body.appendChild(icon);
    body.appendChild(document.createTextNode(" " + item.label.toLowerCase()));
    if (item.details) {
      var details = document.createElement("div");
      details.className = "muted small";
      details.textContent = item.details;
      body.appendChild(details);
    }
    li.appendChild(body);

    var time = document.createElement("time");
    time.className = "muted small";
    time.setAttribute("data-ts", item.ts);
    time.textContent = ago(item.ts);
    li.appendChild(time);
    return li;
  }

  function pollFeed() {
    if (document.hidden) return;
    var since = feed.getAttribute("data-since") || "0";
    fetch(feed.getAttribute("data-feed") + "?since=" + encodeURIComponent(since), { credentials: "same-origin", cache: "no-store" })
      .then(function (res) {
        if (!res.ok) throw new Error("HTTP " + res.status);
        return res.json();
      })
      .then(function (data) {
        clockOffset = data.now - Date.now() / 1000;
        var items = data.items || [];
        if (items.length) {
          var empty = document.getElementById("feed-empty");
          if (empty) empty.remove();
          feed.setAttribute("data-since", items[0].id);
          for (var i = items.length - 1; i >= 0; i--) {
            feed.insertBefore(feedItem(items[i]), feed.firstChild);
          }
          while (feed.children.length > 30) feed.removeChild(feed.lastChild);
        }
        refreshAgo();
        if (status) status.textContent = "updated " + new Date().toLocaleTimeString([], { hour: "numeric", minute: "2-digit", second: "2-digit" });
      })
      .catch(function () {
        if (status) status.textContent = "reconnecting…";
      });
  }

  if (feed) {
    clockOffset = Number(feed.getAttribute("data-now")) - Date.now() / 1000;
    setInterval(pollFeed, 5000);
    document.addEventListener("visibilitychange", function () { if (!document.hidden) pollFeed(); });
  }

  // ---- System Health auto-refresh -------------------------------------------
  var health = document.getElementById("health");
  if (health) {
    var every = Number(health.getAttribute("data-every")) || 15000;
    setInterval(function () {
      if (document.hidden) return;
      fetch(health.getAttribute("data-refresh"), { credentials: "same-origin", cache: "no-store" })
        .then(function (res) {
          // A redirect means the session ended; don't paste a login page into the panel.
          if (!res.ok || res.redirected) throw new Error("HTTP " + res.status);
          return res.text();
        })
        .then(function (html) { health.innerHTML = html; })
        .catch(function () { /* keep the last good reading */ });
    }, every);
  }
})();
