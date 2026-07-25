package com.smartforum;

/**
 * Entry point for the packaged application.
 *
 * <p>The JVM refuses to start a main class that extends {@code Application} when JavaFX is
 * supplied on the classpath rather than the module path, which is how jpackage bundles it.
 * Delegating from a plain class avoids that check.
 */
public final class Launcher {

    private Launcher() {
    }

    public static void main(String[] args) {
        Main.main(args);
    }
}
