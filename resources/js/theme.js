function themeColor(name) {
    return getComputedStyle(document.documentElement).getPropertyValue(`--${name}`).trim();
}

function themeColorAlpha(name, alpha) {
    const hex = themeColor(name).replace('#', '');
    if (hex.length !== 6) {
        return themeColor(name);
    }

    const r = parseInt(hex.slice(0, 2), 16);
    const g = parseInt(hex.slice(2, 4), 16);
    const b = parseInt(hex.slice(4, 6), 16);

    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

function themeChartPalette() {
    return [
        themeColor('sidebar-bg'),
        themeColor('primary'),
        themeColor('primary-light'),
        themeColor('primary-lighter'),
        themeColor('primary-soft'),
        themeColor('primary-muted'),
    ];
}

window.themeColor = themeColor;
window.themeColorAlpha = themeColorAlpha;
window.themeChartPalette = themeChartPalette;
