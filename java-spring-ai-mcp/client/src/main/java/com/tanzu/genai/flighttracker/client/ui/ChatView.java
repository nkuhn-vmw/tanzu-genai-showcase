package com.tanzu.genai.flighttracker.client.ui;

import com.tanzu.genai.flighttracker.client.service.ChatService;
import com.vaadin.flow.component.AttachEvent;
import com.vaadin.flow.component.DetachEvent;
import com.vaadin.flow.component.Key;
import com.vaadin.flow.component.button.Button;
import com.vaadin.flow.component.html.Div;
import com.vaadin.flow.component.html.H1;
import com.vaadin.flow.component.html.Paragraph;
import com.vaadin.flow.component.icon.Icon;
import com.vaadin.flow.component.icon.VaadinIcon;
import com.vaadin.flow.component.orderedlayout.HorizontalLayout;
import com.vaadin.flow.component.orderedlayout.VerticalLayout;
import com.vaadin.flow.component.textfield.TextField;
import com.vaadin.flow.router.PageTitle;
import com.vaadin.flow.router.Route;
import com.vaadin.flow.theme.lumo.LumoUtility;
import lombok.extern.slf4j.Slf4j;
import reactor.core.Disposable;
import reactor.core.publisher.Flux;

import java.util.HashMap;
import java.util.Map;
import java.util.concurrent.atomic.AtomicReference;

/**
 * Main view for the flight tracker chatbot UI.
 */
@Slf4j
@PageTitle("Flight Tracker Chatbot")
@Route("")
public class ChatView extends VerticalLayout {

    private final ChatService chatService;

    private final VerticalLayout chatArea = new VerticalLayout();
    private final TextField messageField = new TextField();
    private final Button sendButton = new Button("Send", new Icon(VaadinIcon.PAPERPLANE));

    private final Map<String, Div> messageContainers = new HashMap<>();
    private final AtomicReference<Disposable> currentChatStream = new AtomicReference<>();

    public ChatView(ChatService chatService) {
        this.chatService = chatService;

        setSizeFull();
        setPadding(true);
        setSpacing(true);

        add(createHeader(), createChatContainer(), createMessageInputArea());

        // Add welcome message
        addAssistantMessage("Hello! I'm your flight tracking assistant. " +
                           "You can ask me about real-time flights, search for airports, " +
                           "airlines, routes and more. How can I help you today?");
    }

    private VerticalLayout createHeader() {
        VerticalLayout header = new VerticalLayout();
        header.setSpacing(false);
        header.setPadding(false);

        H1 title = new H1("Flight Tracker Chatbot");
        title.addClassNames(LumoUtility.FontSize.XXLARGE, LumoUtility.Margin.NONE);

        Paragraph subtitle = new Paragraph("Ask questions about flights, airports, airlines, and routes");
        subtitle.addClassNames(LumoUtility.TextColor.SECONDARY, LumoUtility.Margin.NONE);

        header.add(title, subtitle);
        return header;
    }

    private VerticalLayout createChatContainer() {
        chatArea.setWidth("100%");
        chatArea.setHeight("70vh");
        chatArea.setSpacing(true);
        chatArea.setPadding(true);
        chatArea.getStyle().set("overflow-y", "auto");
        chatArea.getStyle().set("border", "1px solid var(--lumo-contrast-10pct)");
        chatArea.getStyle().set("border-radius", "var(--lumo-border-radius-m)");

        return chatArea;
    }

    private HorizontalLayout createMessageInputArea() {
        HorizontalLayout inputLayout = new HorizontalLayout();
        inputLayout.setWidthFull();
        inputLayout.setSpacing(true);

        messageField.setPlaceholder("Type your message here...");
        messageField.setClearButtonVisible(true);
        messageField.setWidthFull();
        messageField.addKeyPressListener(Key.ENTER, e -> sendMessage());

        sendButton.addThemeVariants(com.vaadin.flow.component.button.ButtonVariant.LUMO_PRIMARY);
        sendButton.addClickListener(e -> sendMessage());

        inputLayout.add(messageField, sendButton);
        inputLayout.expand(messageField);

        return inputLayout;
    }

    private void sendMessage() {
        String message = messageField.getValue().trim();
        if (message.isEmpty()) {
            return;
        }

        addUserMessage(message);

        // Clear input field
        messageField.clear();
        messageField.focus();

        // Cancel any existing stream
        Disposable existingStream = currentChatStream.getAndSet(null);
        if (existingStream != null && !existingStream.isDisposed()) {
            existingStream.dispose();
        }

        // Create response container
        String responseId = "response-" + System.currentTimeMillis();
        Div responseContainer = createMessageContainer("assistant");
        messageContainers.put(responseId, responseContainer);

        // Show thinking indicator
        responseContainer.setText("Thinking...");
        chatArea.add(responseContainer);
        scrollToBottom();

        // Stream response
        Disposable chatStream = chatService.streamingChat(message)
            .subscribe(
                chunk -> {
                    getUI().ifPresent(ui -> ui.access(() -> {
                        Div container = messageContainers.get(responseId);
                        if (container != null) {
                            if (container.getText().equals("Thinking...")) {
                                container.setText(chunk);
                            } else {
                                container.setText(container.getText() + chunk);
                            }
                            scrollToBottom();
                        }
                    }));
                },
                error -> {
                    log.error("Error in chat stream", error);
                    getUI().ifPresent(ui -> ui.access(() -> {
                        Div container = messageContainers.get(responseId);
                        if (container != null) {
                            container.setText("Sorry, an error occurred while processing your request. Please try again.");
                            container.getStyle().set("color", "var(--lumo-error-text-color)");
                        }
                    }));
                },
                () -> {
                    log.debug("Chat stream completed");
                    currentChatStream.set(null);
                }
            );

        currentChatStream.set(chatStream);
    }

    private void addUserMessage(String message) {
        Div messageContainer = createMessageContainer("user");
        messageContainer.setText(message);
        chatArea.add(messageContainer);
        scrollToBottom();
    }

    private void addAssistantMessage(String message) {
        Div messageContainer = createMessageContainer("assistant");
        messageContainer.setText(message);
        chatArea.add(messageContainer);
        scrollToBottom();
    }

    private Div createMessageContainer(String sender) {
        Div container = new Div();
        container.setWidthFull();
        container.getStyle().set("padding", "var(--lumo-space-m)");
        container.getStyle().set("border-radius", "var(--lumo-border-radius-m)");
        container.getStyle().set("margin-bottom", "var(--lumo-space-m)");
        container.getStyle().set("white-space", "pre-wrap");
        container.getStyle().set("word-break", "break-word");

        if ("user".equals(sender)) {
            container.getStyle().set("background-color", "var(--lumo-primary-color-10pct)");
            container.getStyle().set("color", "var(--lumo-primary-text-color)");
            container.getStyle().set("align-self", "flex-end");
            container.getStyle().set("max-width", "80%");
        } else {
            container.getStyle().set("background-color", "var(--lumo-contrast-5pct)");
            container.getStyle().set("color", "var(--lumo-body-text-color)");
            container.getStyle().set("align-self", "flex-start");
            container.getStyle().set("max-width", "80%");
        }

        return container;
    }

    private void scrollToBottom() {
        getUI().ifPresent(ui -> ui.getPage().executeJs(
            "setTimeout(function() { const chatArea = document.querySelector('vaadin-vertical-layout > vaadin-vertical-layout'); " +
            "if (chatArea) { chatArea.scrollTop = chatArea.scrollHeight; } }, 10);"
        ));
    }

    @Override
    protected void onAttach(AttachEvent attachEvent) {
        super.onAttach(attachEvent);
        messageField.focus();
    }

    @Override
    protected void onDetach(DetachEvent detachEvent) {
        super.onDetach(detachEvent);
        Disposable existingStream = currentChatStream.getAndSet(null);
        if (existingStream != null && !existingStream.isDisposed()) {
            existingStream.dispose();
        }
    }
}
