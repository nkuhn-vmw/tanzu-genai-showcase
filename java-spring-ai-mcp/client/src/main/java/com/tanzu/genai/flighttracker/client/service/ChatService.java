package com.tanzu.genai.flighttracker.client.service;

import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.ai.chat.ChatClient;
import org.springframework.ai.chat.ChatResponse;
import org.springframework.ai.chat.messages.Message;
import org.springframework.ai.chat.messages.UserMessage;
import org.springframework.ai.chat.prompt.Prompt;
import org.springframework.stereotype.Service;
import reactor.core.publisher.Flux;
import reactor.core.publisher.Mono;

import java.util.List;

/**
 * Service for interacting with the AI chat functionality.
 */
@Slf4j
@Service
@RequiredArgsConstructor
public class ChatService {

    private final ChatClient chatClient;

    /**
     * Sends a message to the chat model and returns the response.
     *
     * @param message The user's message
     * @return The chat response
     */
    public Mono<String> chat(String message) {
        log.info("Sending message to chat: {}", message);

        UserMessage userMessage = new UserMessage(message);
        Prompt prompt = new Prompt(List.of(userMessage));

        return Mono.fromFuture(chatClient.call(prompt))
            .map(ChatResponse::getResult)
            .map(result -> result.getOutput().getContent());
    }

    /**
     * Sends a message to the chat model and returns the response as a stream.
     *
     * @param message The user's message
     * @return Flux of chat response content
     */
    public Flux<String> streamingChat(String message) {
        log.info("Sending message to streaming chat: {}", message);

        UserMessage userMessage = new UserMessage(message);
        Prompt prompt = new Prompt(List.of(userMessage));

        return chatClient.stream(prompt)
            .map(chatResponse -> chatResponse.getResult().getOutput().getContent());
    }
}
