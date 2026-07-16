package com.smartforum;

import java.io.*;
import java.net.ServerSocket;
import java.net.Socket;
import java.net.URLDecoder;
import java.nio.charset.StandardCharsets;
import java.util.HashMap;
import java.util.Map;
import java.util.function.Consumer;

public class OAuthCallbackServer {

    public void start(Consumer<Map<String, String>> onSuccess) {
        new Thread(() -> {
            try (ServerSocket serverSocket = new ServerSocket(9876)) {
                serverSocket.setSoTimeout(120_000);
                try (Socket socket = serverSocket.accept()) {
                    BufferedReader in = new BufferedReader(new InputStreamReader(socket.getInputStream()));
                    String requestLine = in.readLine(); // GET /?token=x&fname=y... HTTP/1.1

                    Map<String, String> params = new HashMap<>();
                    if (requestLine != null && requestLine.contains("?")) {
                        String query = requestLine.split(" ")[1];
                        String queryString = query.substring(query.indexOf('?') + 1);
                        for (String param : queryString.split("&")) {
                            String[] kv = param.split("=", 2);
                            if (kv.length == 2) {
                                params.put(
                                    URLDecoder.decode(kv[0], StandardCharsets.UTF_8),
                                    URLDecoder.decode(kv[1], StandardCharsets.UTF_8)
                                );
                            }
                        }
                    }

                    String html = "<html><head><link rel=\"stylesheet\" href=\"https://fonts.bunny.net/css?family=Nunito:400,500,600,700\"></head>"
                            + "<body style='font-family:\"Nunito\",sans-serif;background:#0a0f1e;color:white;"
                            + "display:flex;align-items:center;justify-content:center;height:100vh;margin:0'>"
                            + "<div style='text-align:center'>"
                            + "<h2 style='color:#4ade80'>&#10003; Signed in successfully!</h2>"
                            + "<p style='color:#9ca3af'>You can close this tab and return to the app.</p>"
                            + "<script>setTimeout(()=>window.close(),1500)</script>"
                            + "</div></body></html>";
                    byte[] htmlBytes = html.getBytes(StandardCharsets.UTF_8);
                    PrintWriter out = new PrintWriter(socket.getOutputStream());
                    out.println("HTTP/1.1 200 OK");
                    out.println("Content-Type: text/html; charset=utf-8");
                    out.println("Content-Length: " + htmlBytes.length);
                    out.println("Connection: close");
                    out.println();
                    out.flush();
                    socket.getOutputStream().write(htmlBytes);
                    socket.getOutputStream().flush();

                    if (params.containsKey("token")) {
                        onSuccess.accept(params);
                    }
                }
            } catch (Exception e) {
                // silently ignore timeout or errors
            }
        }, "oauth-callback-server").start();
    }
}
