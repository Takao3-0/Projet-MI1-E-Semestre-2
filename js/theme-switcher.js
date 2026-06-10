document.addEventListener("DOMContentLoaded", () => {
    const themeStyle = document.getElementById("theme-style");
    const themeSelect = document.getElementById("theme-select");

    // Fonction pour lire un cookie
    function getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(";").shift();
        return null;
    }

    // Fonction pour écrire un cookie
    function setCookie(name, value, days) {
        let expires = "";
        if (days) {
            const date = new Date();
            date.setTime(date.getTime() + days * 24 * 60 * 60 * 1000);
            expires = "; expires=" + date.toUTCString();
        }
        document.cookie = name + "=" + (value || "") + expires + "; path=/";
    }

    // Appliquer le thème
    function applyTheme(themeName) {
        if (!themeName || themeName === "defaut") {
            if (themeStyle) themeStyle.href = "";
            if (themeSelect) themeSelect.value = "defaut";
        } else {
            let basePath = "css/";
            if (window.location.pathname.includes("/Carte/")) {
                basePath = "../css/";
            }
            if (themeStyle) themeStyle.href = basePath + `theme-${themeName}.css`;
            if (themeSelect) themeSelect.value = themeName;
        }
        setCookie("selected_theme", themeName || "defaut", 365);
    }

    // Initialisation
    const savedTheme = getCookie("selected_theme");
    const validThemes = ["defaut", "clair"];

    if (savedTheme && validThemes.includes(savedTheme)) {
        applyTheme(savedTheme);
    } else {
        // Mode par défaut
        applyTheme("defaut");
    }

    // Événement sur le sélecteur
    if (themeSelect) {
        themeSelect.addEventListener("change", (e) => {
            applyTheme(e.target.value);
        });
    }
});
