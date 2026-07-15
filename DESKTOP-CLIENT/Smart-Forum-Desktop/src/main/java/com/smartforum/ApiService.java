package com.smartforum;

import com.google.gson.JsonObject;
import com.google.gson.JsonParser;

import java.net.URI;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.time.Duration;

public class ApiService {

    private static final String BASE_URL = "http://127.0.0.1:8000/api";
    private static final HttpClient client = HttpClient.newBuilder()
            .connectTimeout(Duration.ofSeconds(10))
            .build();

    public static ApiResponse login(String email, String password) {
        String body = String.format("{\"email\":\"%s\",\"password\":\"%s\"}", email, password);
        return post("/login", body, null);
    }

    public static ApiResponse loginWithToken(String token) {
        return get("/user", token);
    }

    public static ApiResponse register(String fname, String lname, String email, String password, String passwordConfirmation) {
        String body = String.format(
            "{\"Fname\":\"%s\",\"Lname\":\"%s\",\"email\":\"%s\",\"password\":\"%s\",\"password_confirmation\":\"%s\",\"role\":\"student\",\"terms\":\"1\"}",
            fname, lname, email, password, passwordConfirmation
        );
        return post("/register", body, null);
    }

    private static ApiResponse get(String endpoint, String token) {
        try {
            HttpRequest.Builder builder = HttpRequest.newBuilder()
                    .uri(URI.create(BASE_URL + endpoint))
                    .header("Accept", "application/json")
                    .GET();
            if (token != null) builder.header("Authorization", "Bearer " + token);
            HttpResponse<String> response = client.send(builder.build(), HttpResponse.BodyHandlers.ofString());
            JsonObject json = JsonParser.parseString(response.body()).getAsJsonObject();
            // wrap in {user: ...} shape if needed
            if (!json.has("user")) {
                JsonObject wrapped = new JsonObject();
                wrapped.add("user", json);
                return new ApiResponse(response.statusCode(), wrapped);
            }
            return new ApiResponse(response.statusCode(), json);
        } catch (Exception e) {
            JsonObject err = new JsonObject();
            err.addProperty("message", "Cannot connect to server.");
            return new ApiResponse(0, err);
        }
    }

    private static ApiResponse post(String endpoint, String body, String token) {
        try {
            HttpRequest.Builder builder = HttpRequest.newBuilder()
                    .uri(URI.create(BASE_URL + endpoint))
                    .header("Content-Type", "application/json")
                    .header("Accept", "application/json")
                    .POST(HttpRequest.BodyPublishers.ofString(body));

            if (token != null) builder.header("Authorization", "Bearer " + token);

            HttpResponse<String> response = client.send(builder.build(), HttpResponse.BodyHandlers.ofString());
            JsonObject json = JsonParser.parseString(response.body()).getAsJsonObject();
            return new ApiResponse(response.statusCode(), json);

        } catch (Exception e) {
            JsonObject err = new JsonObject();
            err.addProperty("message", "Cannot connect to server. Make sure the server is running.");
            return new ApiResponse(0, err);
        }
    }

    public record ApiResponse(int statusCode, JsonObject body) {
        public boolean isSuccess() { return statusCode >= 200 && statusCode < 300; }
        public String getMessage() {
            if (body.has("message")) return body.get("message").getAsString();
            return "Unknown error";
        }
    }
}
