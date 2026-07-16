package com.smartforum.util;

import com.lowagie.text.Document;
import com.lowagie.text.Font;
import com.lowagie.text.FontFactory;
import com.lowagie.text.PageSize;
import com.lowagie.text.Paragraph;
import com.lowagie.text.Phrase;
import com.lowagie.text.pdf.PdfWriter;
import com.smartforum.model.Post;

import java.awt.Color;
import java.io.File;
import java.io.FileOutputStream;
import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.util.List;
import java.util.Locale;

public final class TopicPdfExporter {

    private TopicPdfExporter() {
    }

    public static void export(File destination, String topicTitle, String groupName, List<Post> posts)
            throws Exception {
        Document document = new Document(PageSize.A4, 36, 36, 48, 36);
        try (FileOutputStream outputStream = new FileOutputStream(destination)) {
            PdfWriter.getInstance(document, outputStream);
            document.open();

            Font titleFont = FontFactory.getFont(FontFactory.HELVETICA, 16, Font.BOLD, Color.BLACK);
            Font metaFont = FontFactory.getFont(FontFactory.HELVETICA, 11, Font.NORMAL, new Color(102, 102, 102));
            Font authorFont = FontFactory.getFont(FontFactory.HELVETICA, 11, Font.BOLD, new Color(22, 101, 52));
            Font bodyFont = FontFactory.getFont(FontFactory.HELVETICA, 11, Font.NORMAL, Color.BLACK);
            Font timeFont = FontFactory.getFont(FontFactory.HELVETICA, 9, Font.NORMAL, new Color(120, 120, 120));

            Paragraph title = new Paragraph(topicTitle == null ? "Discussion" : topicTitle, titleFont);
            title.setSpacingAfter(4);
            document.add(title);

            String exportedAt = LocalDateTime.now()
                    .format(DateTimeFormatter.ofPattern("MMM d, yyyy h:mm a", Locale.ENGLISH));
            Paragraph meta = new Paragraph(
                    (groupName == null ? "" : groupName + " — ") + "Exported " + exportedAt,
                    metaFont);
            meta.setSpacingAfter(16);
            document.add(meta);

            if (posts == null || posts.isEmpty()) {
                Paragraph empty = new Paragraph("No messages in this discussion.", bodyFont);
                document.add(empty);
                return;
            }

            for (Post post : posts) {
                Paragraph authorLine = new Paragraph();
                authorLine.add(new Phrase(post.getAuthorName() == null ? "Unknown" : post.getAuthorName(), authorFont));
                authorLine.add(new Phrase("  " + formatTime(post.getCreatedAt()), timeFont));
                authorLine.setSpacingBefore(10);
                authorLine.setSpacingAfter(4);
                document.add(authorLine);

                Paragraph content = new Paragraph(post.getContent() == null ? "" : post.getContent(), bodyFont);
                content.setSpacingAfter(8);
                document.add(content);
            }
        } finally {
            if (document.isOpen()) {
                document.close();
            }
        }
    }

    private static String formatTime(LocalDateTime time) {
        if (time == null) {
            return "";
        }
        return time.format(DateTimeFormatter.ofPattern("MMM d, yyyy h:mm a", Locale.ENGLISH));
    }
}
