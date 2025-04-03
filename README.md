# Tanzu GenAI Showcase

This repository contains multiple examples of applications that can be deployed on [Tanzu Platform for Cloud Foundry](https://techdocs.broadcom.com/us/en/vmware-tanzu/platform/tanzu-platform-for-cloud-foundry/10-0/tpcf/concepts-overview.html), demonstrating the use of [GenAI tile](https://techdocs.broadcom.com/us/en/vmware-tanzu/platform-services/genai-on-tanzu-platform-for-cloud-foundry/10-0/ai-cf/index.html)'s LLM capabilities.

## Overview

Each subdirectory in this repository represents a different tech stack implementation showcasing how to integrate with GenAI LLM services. These examples cover a variety of use cases, from chatbots and recommendation systems to research assistants and event discovery applications.

## Samples

* [.NET with Microsoft.Extensions.AI and Semantic Kernel](./dotnet-extensions-ai/README.md) - A transportation mode recommendation bot that helps users choose the best way to travel between locations.
* [Go + Fiber + LangChainGo](./go-fiber-langchaingo/README.md) - A chatbot that interacts with the Congress.gov API to provide information about bills, amendments, and more.
* [Java + Spring Boot + LangGraph + Model Context Protocol + Angular](./java-spring-langgraph-mcp-angular/README.md) - An event recommendation chatbot that suggests events based on user preferences and location.
* [Java + Spring AI + Model Context Protocol + Vaadin](./java-spring-ai-mcp/README.md) - A flight tracking chatbot that provides real-time flight information through a natural language interface.
* [JavaScript + LangChain + React](./js-langchain-react/README.md) - A news aggregation application that searches for articles and generates concise summaries.
* [PHP + Symfony + Neuron](./php-symfony-neuron/README.md) - A company research application that gathers financial data and generates reports.
* [Python + Django + CrewAI](./py-django-crewai/README.md) - A movie booking chatbot that coordinates multiple AI agents to find movies and nearby show times.
* [Python + Pyramid + Agno](./py-pyramid-agno/README.md) - An Airbnb search assistant that helps users find accommodations and view listing details.
* [Ruby + Sinatra + FastMCP](./ruby-sinatra-fastmcp/README.md) - A flight tracking chatbot with a modern web UI and AI integration that provides real-time flight information.

## Getting Started

Each tech stack implementation includes its own README.md with specific instructions for:

* Prerequisites
* Local development setup
* Building and testing
* Deploying to Tanzu Platform for Cloud Foundry

## Common Structure

All example projects follow a similar structure and address the following aspects:

1. **What is it?** - A description of the application and its capabilities
2. **Prerequisites** - Required tools, dependencies, and environment setup
3. **Building** - How to build and package the application
4. **Local Development** - Running and testing the application locally
5. **Deployment** - Deploying to Tanzu Platform for Cloud Foundry
6. **Tech Stack** - Detailed information about the technologies used
7. **Project Structure** - Explanation of the repository organization

## Deployment on Tanzu Platform for Cloud Foundry

All examples are designed to be deployable with `cf push` and can be bound to a GenAI LLM service instance.

### Service Binding

The examples demonstrate two approaches to consuming LLM services:

1. **Service Instance Binding**: Applications are bound to an LLM service instance, and the credentials are automatically injected.
2. **Manual Configuration**: Service keys are created and the applications are manually configured to use those credentials.

## Contributing

Feel free to add new tech stack examples or improve existing ones.
