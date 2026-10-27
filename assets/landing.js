(function () {
    "use strict";

    var toggle = document.getElementById("navToggle");
    var menu   = document.getElementById("navMenu");

    if (!toggle || !menu) return;

    // Pag-click sa hamburger icon (bukas/sara)
    toggle.addEventListener("click", function () {
        var open = menu.classList.toggle("open");
        toggle.setAttribute("aria-expanded", open ? "true" : "false");
        toggle.setAttribute("aria-label", open ? "Close menu" : "Open menu");
    });

    // Isara ang menu kapag may pinindot na link (mobile view)
    menu.addEventListener("click", function (e) {
        if (e.target.tagName === "A" && window.innerWidth <= 768) {
            menu.classList.remove("open");
            toggle.setAttribute("aria-expanded", "false");
            toggle.setAttribute("aria-label", "Open menu");
        }
    });

    // Isara ang menu kapag pinalaki ang window (bumalik sa desktop view)
    window.addEventListener("resize", function () {
        if (window.innerWidth > 768 && menu.classList.contains("open")) {
            menu.classList.remove("open");
            toggle.setAttribute("aria-expanded", "false");
        }
    });
})();