package com.smartforum.util;

import javafx.scene.control.Button;

/**
 * Inline JavaFX button styling. Required because Modena's layered button
 * backgrounds often ignore author CSS alone.
 */
public final class ButtonStyles {

    public static final String PRIMARY = "#16a34a";
    public static final String PRIMARY_DARK = "#15803d";

    private ButtonStyles() {
    }

    public static void applyPrimary(Button button) {
        applyPrimary(button, false);
    }

    public static void applyPrimary(Button button, boolean small) {
        if (button == null) {
            return;
        }
        if (!button.getStyleClass().contains("btn-primary")) {
            button.getStyleClass().add("btn-primary");
        }
        if (small && !button.getStyleClass().contains("btn-sm")) {
            button.getStyleClass().add("btn-sm");
        }
        button.hoverProperty().addListener((obs, wasHover, isHover) ->
                button.setStyle(primaryStyle(isHover, small)));
        button.setStyle(primaryStyle(button.isHover(), small));
    }

    public static void applyOutlinePrimary(Button button) {
        applyOutlinePrimary(button, false);
    }

    public static void applyOutlinePrimary(Button button, boolean small) {
        if (button == null) {
            return;
        }
        if (!button.getStyleClass().contains("btn-outline-primary")) {
            button.getStyleClass().add("btn-outline-primary");
        }
        if (small && !button.getStyleClass().contains("btn-sm")) {
            button.getStyleClass().add("btn-sm");
        }
        button.hoverProperty().addListener((obs, wasHover, isHover) ->
                button.setStyle(outlinePrimaryStyle(isHover, small)));
        button.setStyle(outlinePrimaryStyle(button.isHover(), small));
    }

    private static String primaryStyle(boolean hover, boolean small) {
        String bg = hover ? PRIMARY_DARK : PRIMARY;
        return baseStyle(bg, "white", "transparent", 0, small);
    }

    private static String outlinePrimaryStyle(boolean hover, boolean small) {
        String bg = hover ? PRIMARY : "white";
        String text = hover ? "white" : PRIMARY_DARK;
        return baseStyle(bg, text, PRIMARY, 1, small);
    }

    private static String baseStyle(String background, String text, String border, int borderWidth, boolean small) {
        String padding = small ? "4 10 4 10" : "7 14 7 14";
        String fontSize = small ? "11px" : "12px";
        return "-fx-background-color: " + background + ";"
                + "-fx-background-insets: 0;"
                + "-fx-background-radius: 8;"
                + "-fx-border-color: " + border + ";"
                + "-fx-border-width: " + borderWidth + ";"
                + "-fx-border-radius: 8;"
                + "-fx-text-fill: " + text + ";"
                + "-fx-font-weight: bold;"
                + "-fx-font-size: " + fontSize + ";"
                + "-fx-padding: " + padding + ";"
                + "-fx-cursor: hand;"
                + "-fx-effect: null;";
    }
}
