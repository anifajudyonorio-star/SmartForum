package com.smartforum.util;

import javafx.scene.text.Font;

import java.io.InputStream;
import java.util.List;

public final class FontLoader {

    private static final List<String> NUNITO_FONTS = List.of(
            "/com/smartforum/fonts/Nunito-Regular.ttf",
            "/com/smartforum/fonts/Nunito-Medium.ttf",
            "/com/smartforum/fonts/Nunito-SemiBold.ttf",
            "/com/smartforum/fonts/Nunito-Bold.ttf"
    );

    private FontLoader() {
    }

    public static void loadAppFonts() {
        for (String path : NUNITO_FONTS) {
            try (InputStream stream = FontLoader.class.getResourceAsStream(path)) {
                if (stream != null) {
                    Font.loadFont(stream, 12);
                }
            } catch (Exception ignored) {
                // Fall back to system fonts if a file is missing.
            }
        }
    }
}
