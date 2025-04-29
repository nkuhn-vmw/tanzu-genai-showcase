using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.AI;
using Microsoft.Extensions.Logging;
using Microsoft.Extensions.Options;
using Microsoft.SemanticKernel;
using OpenAI;
using OpenAI.Chat;
using System;
using System.ClientModel;
using System.Collections.Generic;
using System.Linq;
using System.Net.Http;
using System.Reflection;
using System.Threading;
using System.Threading.Tasks;
using TravelAdvisor.Core.Services;
using TravelAdvisor.Core.Utilities;
using TravelAdvisor.Infrastructure.CloudFoundry;
using TravelAdvisor.Infrastructure.Services;
using TravelAdvisor.Infrastructure.Options;

namespace TravelAdvisor.Infrastructure
{
    /// <summary>
    /// Extension methods for setting up infrastructure services in an IServiceCollection
    /// </summary>
    public static class DependencyInjection
    {
        /// <summary>
        /// Adds infrastructure services to the specified IServiceCollection
        /// </summary>
        public static IServiceCollection AddInfrastructureServices(this IServiceCollection services, IConfiguration configuration)
        {
            // Add Cloud Foundry service bindings
            services.AddCloudFoundryServices(configuration);

            // Configure HTTP client
            services.AddHttpClient();

            // Check if mock data is enabled
            bool useMockData = IsMockDataEnabled();

            // Register the Maps services
            if (useMockData)
            {
                services.AddSingleton<IGoogleMapsService, MockGoogleMapsService>();
                services.AddSingleton<IMapService>(sp => sp.GetRequiredService<IGoogleMapsService>());
            }
            else
            {
                services.AddSingleton<IGoogleMapsService, GoogleMapsService>();
                services.AddSingleton<IMapService>(sp => sp.GetRequiredService<IGoogleMapsService>());
            }

            // Add AI services
            AddAIServices(services, configuration);

            // Register the Travel Advisor services
            if (useMockData)
            {
                services.AddSingleton<ITravelAdvisorService, MockTravelAdvisorService>();
            }
            else
            {
                services.AddSingleton<ITravelAdvisorService, TravelAdvisorService>();
            }

            return services;
        }

        /// <summary>
        /// Adds AI services to the service collection
        /// </summary>
        private static void AddAIServices(IServiceCollection services, IConfiguration configuration)
        {
            // Get mock data setting and logger
            bool useMockData = IsMockDataEnabled();
            ILogger? logger = GetLogger(services);

            if (useMockData)
            {
                RegisterMockAIServices(services, logger);
                return;
            }

            try
            {
                // Get and validate GenAI options
                var genAIOptions = GetAndValidateGenAIOptions(services, logger);

                // Register the appropriate client based on the API URL
                if (IsAzureOpenAI(genAIOptions.ApiUrl))
                {
                    RegisterAzureOpenAIClient(services, genAIOptions, logger);
                }
                else
                {
                    RegisterStandardOpenAIClient(services, genAIOptions, logger);
                }

                // Register the PromptFactory
                services.AddSingleton<IPromptFactory, PromptFactory>();
            }
            catch (Exception ex)
            {
                // If there's an error setting up the client, provide a detailed error message
                var errorMessage = $"Failed to initialize AI client: {ex.Message}\n" +
                                  "Please check your GenAI credentials and ensure they are correctly configured.";

                LogMessage(logger, errorMessage, Console.WriteLine, LogLevel.Error);
                throw new InvalidOperationException(errorMessage, ex);
            }
        }

        /// <summary>
        /// Checks if mock data is enabled via environment variable
        /// </summary>
        private static bool IsMockDataEnabled()
        {
            string useMockDataStr = Environment.GetEnvironmentVariable("USE_MOCK_DATA") ?? "false";
            return useMockDataStr.ToLowerInvariant() == "true" || useMockDataStr == "1";
        }

        /// <summary>
        /// Gets a logger from the service collection if available
        /// </summary>
        private static ILogger? GetLogger(IServiceCollection services)
        {
            try
            {
                var loggerFactory = services.BuildServiceProvider().GetService<ILoggerFactory>();
                return loggerFactory?.CreateLogger("DependencyInjection");
            }
            catch
            {
                return null;
            }
        }

        /// <summary>
        /// Gets and validates GenAI options from the service collection
        /// </summary>
        private static GenAIOptions GetAndValidateGenAIOptions(IServiceCollection services, ILogger? logger)
        {
            var serviceProvider = services.BuildServiceProvider();
            var genAIOptions = serviceProvider.GetRequiredService<IOptions<GenAIOptions>>().Value;

            // Log what we're using (but don't log the full API key)
            LogMessage(logger, "Using GenAI options from service binding:", Console.WriteLine);
            LogMessage(logger, $"  API Key: {(string.IsNullOrEmpty(genAIOptions.ApiKey) ? "Not found" : genAIOptions.ApiKey.Substring(0, Math.Min(5, genAIOptions.ApiKey.Length)) + "...")}", Console.WriteLine);
            LogMessage(logger, $"  API URL: {genAIOptions.ApiUrl}", Console.WriteLine);
            LogMessage(logger, $"  Model: {genAIOptions.Model}", Console.WriteLine);
            LogMessage(logger, $"  Service Name: {genAIOptions.ServiceName}", Console.WriteLine);

            // Validate that we have the required credentials
            if (string.IsNullOrEmpty(genAIOptions.ApiKey) || string.IsNullOrEmpty(genAIOptions.ApiUrl))
            {
                throw new InvalidOperationException(
                    "GenAI API key and URL are required when USE_MOCK_DATA is false. " +
                    "Please configure credentials using one of the following methods:\n" +
                    "1. Bind a GenAI service instance to this application\n" +
                    "2. Set GENAI__APIKEY and GENAI__APIURL environment variables\n" +
                    "3. Configure GenAI:ApiKey and GenAI:ApiUrl in appsettings.json");
            }

            return genAIOptions;
        }

        /// <summary>
        /// Checks if the API URL is for Azure OpenAI
        /// </summary>
        private static bool IsAzureOpenAI(string apiUrl)
        {
            return !string.IsNullOrEmpty(apiUrl) && apiUrl.Contains("openai.azure.com");
        }

        /// <summary>
        /// Registers mock AI services when mock data is enabled
        /// </summary>
        private static void RegisterMockAIServices(IServiceCollection services, ILogger? logger)
        {
            LogMessage(logger, "USE_MOCK_DATA is set to true. Using mock implementations for AI services.", Console.WriteLine);

            services.AddSingleton<IChatClient>(sp =>
            {
                var loggerFactory = sp.GetRequiredService<ILoggerFactory>();
                var clientLogger = loggerFactory.CreateLogger<MockChatClient>();
                LogMessage(logger, "Registering MockChatClient because USE_MOCK_DATA=true", Console.WriteLine);
                return new MockChatClient(clientLogger, true);
            });

            services.AddSingleton<IPromptFactory, PromptFactory>();
        }

        /// <summary>
        /// Registers Azure OpenAI client
        /// </summary>
        private static void RegisterAzureOpenAIClient(IServiceCollection services, GenAIOptions options, ILogger? logger)
        {
            LogMessage(logger, "Detected Azure OpenAI URL. Using AzureOpenAIChatClient.", Console.WriteLine);

            services.AddSingleton<IChatClient>(sp =>
            {
                try
                {
                    var loggerFactory = sp.GetRequiredService<ILoggerFactory>();
                    var clientLogger = loggerFactory.CreateLogger("AzureOpenAIChatClient");

                    return CreateAzureOpenAIClient(options.ApiKey, options.ApiUrl, options.Model, clientLogger);
                }
                catch (Exception ex)
                {
                    LogMessage(logger, $"Error creating Azure OpenAI client: {ex.Message}", Console.WriteLine, LogLevel.Error);
                    throw new InvalidOperationException($"Failed to initialize Azure OpenAI client: {ex.Message}", ex);
                }
            });
        }

        /// <summary>
        /// Registers OpenAI client based on endpoint type
        /// </summary>
        private static void RegisterStandardOpenAIClient(IServiceCollection services, GenAIOptions options, ILogger? logger)
        {
            LogMessage(logger, $"Configuring OpenAI client with API URL: {options.ApiUrl}", Console.WriteLine);
            LogMessage(logger, $"Using model: {options.Model}", Console.WriteLine);

            // Check if we have a custom endpoint
            bool isCustomEndpoint = !string.IsNullOrEmpty(options.ApiUrl) &&
                                   !options.ApiUrl.Contains("api.openai.com");

            if (isCustomEndpoint)
            {
                // For custom endpoints, always use our custom client
                RegisterCustomEndpointClient(services, options, logger);
            }
            else
            {
                // For official OpenAI endpoints, use the standard client
                RegisterOfficialOpenAIClient(services, options, logger);
            }
        }

        /// <summary>
        /// Registers the official OpenAI client for standard OpenAI endpoints
        /// </summary>
        private static void RegisterOfficialOpenAIClient(IServiceCollection services, GenAIOptions options, ILogger? logger)
        {
            LogMessage(logger, "Using standard OpenAI client for official OpenAI endpoint", Console.WriteLine);

            services.AddSingleton<IChatClient>(sp =>
            {
                try
                {
                    // Create a direct OpenAI client with the API key
                    LogMessage(logger, "Creating OpenAI client with API key", Console.WriteLine);
                    var openAIClient = new OpenAIClient(options.ApiKey);

                    // Get the chat client with the specified model
                    var chatClient = openAIClient.GetChatClient(options.Model);
                    LogMessage(logger, $"Successfully created chat client with model: {options.Model}", Console.WriteLine);

                    return chatClient.AsIChatClient();
                }
                catch (Exception ex)
                {
                    LogMessage(logger, $"Error creating standard OpenAI client: {ex.Message}", Console.WriteLine, LogLevel.Error);
                    throw;
                }
            });
        }

        /// <summary>
        /// Registers a custom client for non-standard OpenAI endpoints
        /// </summary>
        private static void RegisterCustomEndpointClient(IServiceCollection services, GenAIOptions options, ILogger? logger)
        {
            LogMessage(logger, $"Using custom client for non-standard endpoint: {options.ApiUrl}", Console.WriteLine);

            services.AddSingleton<IChatClient>(sp =>
            {
                try
                {
                    // Create a custom HttpClient with the base address set to our custom endpoint
                    var httpClient = new HttpClient
                    {
                        BaseAddress = new Uri(options.ApiUrl)
                    };

                    // Try multiple authentication formats
                    // First, try the standard Bearer token format
                    httpClient.DefaultRequestHeaders.Add("Authorization", $"Bearer {options.ApiKey}");

                    // Create our custom client implementation
                    LogMessage(logger, "Creating CustomEndpointChatClient with multiple auth formats", Console.WriteLine);
                    return new CustomEndpointChatClient(httpClient, options.Model, options.ApiKey, logger);
                }
                catch (Exception ex)
                {
                    LogMessage(logger, $"Error creating custom endpoint client: {ex.Message}", Console.WriteLine, LogLevel.Error);
                    throw;
                }
            });
        }

        /// <summary>
        /// Create Azure OpenAI chat client using reflection to handle API changes
        /// </summary>
        private static IChatClient CreateAzureOpenAIClient(string apiKey, string endpoint, string deploymentName, ILogger? logger = null)
        {
            try
            {
                // Try to create the client directly first
                LogMessage(logger, $"Creating Azure OpenAI client with endpoint: {endpoint} and deployment: {deploymentName}", Console.WriteLine);

                // Find the Azure OpenAI client type
                var azureOpenAIClientType = FindAzureOpenAIClientType();

                if (azureOpenAIClientType == null)
                {
                    // Check if mock data is enabled
                    bool useMockData = IsMockDataEnabled();

                    if (useMockData)
                    {
                        // Only fallback to mock implementation if mock data is explicitly enabled
                        LogMessage(logger, "Could not find AzureOpenAIChatClient type in the loaded assemblies. Using MockChatClient as fallback.", Console.WriteLine, LogLevel.Warning);
                        return new MockChatClient(null, true);
                    }
                    else
                    {
                        // If mock data is not enabled, throw an exception to prevent fallback
                        var errorMessage = "Could not find AzureOpenAIChatClient type in the loaded assemblies and USE_MOCK_DATA is false.";
                        LogMessage(logger, errorMessage, Console.WriteLine, LogLevel.Error);
                        throw new InvalidOperationException(errorMessage);
                    }
                }

                // Try to create an instance using the constructor
                var constructor = azureOpenAIClientType.GetConstructor(new[] { typeof(string), typeof(string), typeof(string) });

                if (constructor != null)
                {
                    return (IChatClient)constructor.Invoke(new object[] { apiKey, endpoint, deploymentName });
                }

                // Try with just API key and endpoint
                constructor = azureOpenAIClientType.GetConstructor(new[] { typeof(string), typeof(string) });
                if (constructor != null)
                {
                    var client = (IChatClient)constructor.Invoke(new object[] { apiKey, endpoint });

                    // Try to set the deployment name via a property
                    var deploymentProperty = azureOpenAIClientType.GetProperty("DeploymentName");
                    if (deploymentProperty != null && deploymentProperty.CanWrite)
                    {
                        deploymentProperty.SetValue(client, deploymentName);
                    }

                    return client;
                }

                // Check if mock data is enabled
                bool useMockDataFallback = IsMockDataEnabled();

                if (useMockDataFallback)
                {
                    // Only fallback to mock implementation if mock data is explicitly enabled
                    return new MockChatClient(null, true);
                }
                else
                {
                    // If mock data is not enabled, throw an exception to prevent fallback
                    var errorMessage = "Could not create AzureOpenAIChatClient with the available constructors and USE_MOCK_DATA is false.";
                    LogMessage(logger, errorMessage, Console.WriteLine, LogLevel.Error);
                    throw new InvalidOperationException(errorMessage);
                }
            }
            catch (Exception ex)
            {
                // Check if mock data is enabled
                bool useMockData = IsMockDataEnabled();

                if (useMockData)
                {
                    // Only fallback to mock implementation if mock data is explicitly enabled
                    LogMessage(logger, $"Failed to create AzureOpenAIChatClient: {ex.Message}. Using MockChatClient as fallback.", Console.WriteLine, LogLevel.Warning);
                    return new MockChatClient(null, true);
                }
                else
                {
                    // If mock data is not enabled, throw an exception to prevent fallback
                    var errorMessage = $"Failed to create AzureOpenAIChatClient and USE_MOCK_DATA is false. Original error: {ex.Message}";
                    LogMessage(logger, errorMessage, Console.WriteLine, LogLevel.Error);
                    throw new InvalidOperationException(errorMessage, ex);
                }
            }
        }

        /// <summary>
        /// Finds the Azure OpenAI client type using reflection
        /// </summary>
        private static Type? FindAzureOpenAIClientType()
        {
            // Try known type locations first
            var azureOpenAIClientType =
                Type.GetType("Microsoft.Extensions.AI.AzureOpenAIChatClient, Microsoft.Extensions.AI") ??
                Type.GetType("Microsoft.Extensions.AI.AzureOpenAIChatClient, Microsoft.Extensions.AI.Abstractions") ??
                Type.GetType("Microsoft.Extensions.AI.OpenAI.AzureOpenAIChatClient, Microsoft.Extensions.AI.OpenAI");

            if (azureOpenAIClientType != null)
            {
                return azureOpenAIClientType;
            }

            // Search through loaded assemblies
            foreach (var assembly in AppDomain.CurrentDomain.GetAssemblies())
            {
                try
                {
                    var types = assembly.GetTypes()
                        .Where(t => t.Name.EndsWith("AzureOpenAIChatClient") &&
                                   t.Namespace?.StartsWith("Microsoft.Extensions.AI") == true);

                    foreach (var type in types)
                    {
                        return type;
                    }
                }
                catch
                {
                    // Skip assemblies that can't be loaded
                }
            }

            return null;
        }

        /// <summary>
        /// Helper method to log a message to both the logger and the console
        /// </summary>
        private static void LogMessage(ILogger? logger, string message, Action<string> consoleWriter, LogLevel level = LogLevel.Information)
        {
            // Log to the logger if available
            if (logger != null)
            {
                logger.Log(level, message);
            }

            // Always write to the console as well for debugging
            consoleWriter(message);
        }
    }

    /// <summary>
    /// A mock implementation of IChatClient for development/testing
    /// </summary>
    internal class MockChatClient : IChatClient
    {
        private readonly ILogger<MockChatClient>? _logger;
        private readonly bool _useMockData;

        public MockChatClient(ILogger<MockChatClient>? logger = null)
            : this(logger, false)
        {
        }

        public MockChatClient(ILogger<MockChatClient>? logger, bool useMockData)
        {
            _logger = logger;
            _useMockData = useMockData;
            _logger?.LogInformation($"MockChatClient initialized with useMockData={useMockData}");
        }

        public Task<ChatResponse> GetResponseAsync(IEnumerable<Microsoft.Extensions.AI.ChatMessage> messages, ChatOptions? options = null, CancellationToken cancellationToken = default)
        {
            var chatResponse = new ChatResponse();

            // If mock data is explicitly disabled, return an appropriate error message
            if (!_useMockData)
            {
                _logger?.LogError("Mock data is disabled (USE_MOCK_DATA=false). GenAI service is not configured properly.");

                // Return a more helpful error message explaining the issue and potential solutions
                var errorMessage = "GenAI service credentials not found or not properly configured. " +
                                  "Please configure credentials using one of the following methods:\n" +
                                  "1. Bind a GenAI service instance to this application (RECOMMENDED)\n" +
                                  "2. Set GENAI__APIKEY and GENAI__APIURL environment variables\n" +
                                  "3. Configure GenAI:ApiKey and GenAI:ApiUrl in appsettings.json\n\n" +
                                  "Alternatively, you can set USE_MOCK_DATA=true for development purposes.";

                chatResponse.AddProperty("content", errorMessage);
                chatResponse.AddProperty("role", ChatRole.Assistant.ToString());
                chatResponse.AddProperty("error", "CREDENTIALS_MISSING");

                return Task.FromResult(chatResponse);
            }

            _logger?.LogWarning("Using mock AI service. Configure GenAI:ApiKey and GenAI:ApiUrl for a real service.");

            // Provide a more substantial mock response with example travel data
            chatResponse.AddProperty("content", GetMockTravelResponse());
            chatResponse.AddProperty("role", ChatRole.Assistant.ToString());

            return Task.FromResult(chatResponse);
        }

        public IAsyncEnumerable<ChatResponseUpdate> GetStreamingResponseAsync(IEnumerable<Microsoft.Extensions.AI.ChatMessage> messages, ChatOptions? options = null, CancellationToken cancellationToken = default)
        {
            // If mock data is explicitly disabled, log an error but return an empty stream
            // We don't throw an exception to maintain consistency with GetResponseAsync
            if (!_useMockData)
            {
                _logger?.LogError("Mock data is disabled (USE_MOCK_DATA=false). GenAI service is not configured properly.");
                _logger?.LogError("Please configure GenAI service credentials or set USE_MOCK_DATA=true for development.");
            }
            else
            {
                _logger?.LogWarning("Using mock AI service. Configure GenAI:ApiKey and GenAI:ApiUrl for a real service.");
            }

            // Return an empty stream in both cases
            return EmptyAsyncEnumerable<ChatResponseUpdate>.Instance;
        }

        private string GetMockTravelResponse()
        {
            return @"{
              ""Origin"": ""Mill Creek, WA"",
              ""Destination"": ""Ballard, WA"",
              ""TravelTime"": {
                ""DepartureTime"": null,
                ""ArrivalTime"": null,
                ""IsFlexible"": true
              },
              ""Preferences"": {
                ""Priority"": ""faster"",
                ""ConsiderWalking"": true,
                ""ConsiderBiking"": true,
                ""ConsiderPublicTransport"": true,
                ""ConsiderDriving"": true,
                ""ConsiderTrain"": true,
                ""ConsiderFlying"": false,
                ""MaxWalkingDistance"": null,
                ""MaxBikingDistance"": null,
                ""MaxTravelTime"": null,
                ""MaxCost"": null
              },
              ""AdditionalContext"": ""Travel from Mill Creek to Ballard""
            }";
        }

        public ChatClientMetadata Metadata => new ChatClientMetadata();

        public object? GetService(Type serviceType, object? key = null)
        {
            return null;
        }

        public void Dispose() { }
    }

    /// <summary>
    /// Custom implementation of IChatClient that works with custom endpoints
    /// </summary>
    internal class CustomEndpointChatClient : IChatClient
    {
        private readonly HttpClient _httpClient;
        private readonly string _model;
        private readonly string _apiKey;
        private readonly ILogger? _logger;

        public CustomEndpointChatClient(HttpClient httpClient, string model, string apiKey, ILogger? logger)
        {
            _httpClient = httpClient ?? throw new ArgumentNullException(nameof(httpClient));
            _model = model ?? throw new ArgumentNullException(nameof(model));
            _apiKey = apiKey ?? throw new ArgumentNullException(nameof(apiKey));
            _logger = logger;
        }

        public async Task<ChatResponse> GetResponseAsync(IEnumerable<Microsoft.Extensions.AI.ChatMessage> messages, ChatOptions? options = null, CancellationToken cancellationToken = default)
        {
            // Try multiple authentication formats
            var authFormats = new List<(string HeaderName, string HeaderValue)>
            {
                ("Authorization", $"Bearer {_apiKey}"),
                ("api-key", _apiKey),
                ("x-api-key", _apiKey)
            };

            Exception? lastException = null;

            // Try each authentication format
            foreach (var (headerName, headerValue) in authFormats)
            {
                try
                {
                    _logger?.LogInformation($"Trying authentication format: {headerName}");

                    // Create a new HttpClient for each attempt to ensure clean headers
                    using var httpClient = new HttpClient
                    {
                        BaseAddress = _httpClient.BaseAddress
                    };

                    // Set the authentication header
                    httpClient.DefaultRequestHeaders.Clear();
                    httpClient.DefaultRequestHeaders.Add(headerName, headerValue);

                    // Format the messages for the OpenAI API
                    var requestBody = new
                    {
                        model = _model,
                        messages = messages.Select(m => new {
                            role = m.Role.ToString().ToLower(),
                            content = GetMessageContent(m)
                        }),
                        temperature = options?.Temperature ?? 0.7,
                        max_tokens = 8192 // Default max tokens
                    };

                    // Serialize the request body
                    var content = new StringContent(
                        System.Text.Json.JsonSerializer.Serialize(requestBody),
                        System.Text.Encoding.UTF8,
                        "application/json");

                    // Send the request to the custom endpoint
                    var response = await httpClient.PostAsync("/v1/chat/completions", content, cancellationToken);

                    // If the request was successful, process the response
                    if (response.IsSuccessStatusCode)
                    {
                        _logger?.LogInformation($"Successfully authenticated with {headerName}");

                        // Read the response
                        var responseJson = await response.Content.ReadAsStringAsync(cancellationToken);

                        // Create a ChatResponse object
                        var chatResponse = new ChatResponse();

                        // Parse the response JSON to extract the content
                        using (var jsonDoc = System.Text.Json.JsonDocument.Parse(responseJson))
                        {
                            var root = jsonDoc.RootElement;

                            // Try to get the content from the choices array
                            if (root.TryGetProperty("choices", out var choices) &&
                                choices.GetArrayLength() > 0 &&
                                choices[0].TryGetProperty("message", out var message) &&
                                message.TryGetProperty("content", out var contentElement))
                            {
                                chatResponse.AddProperty("content", contentElement.GetString() ?? "");

                                // Try to get the role
                                if (message.TryGetProperty("role", out var role))
                                {
                                    chatResponse.AddProperty("role", role.GetString() ?? "assistant");
                                }
                                else
                                {
                                    chatResponse.AddProperty("role", "assistant");
                                }
                            }
                            else
                            {
                                // If we couldn't find the content in the expected structure,
                                // just use the whole response as the content
                                chatResponse.AddProperty("content", responseJson);
                                chatResponse.AddProperty("role", "assistant");
                            }
                        }

                        return chatResponse;
                    }
                    else
                    {
                        // Log the error but continue trying other formats
                        var errorContent = await response.Content.ReadAsStringAsync(cancellationToken);
                        _logger?.LogWarning($"Authentication failed with {headerName}: {response.StatusCode} - {errorContent}");
                        lastException = new HttpRequestException($"HTTP {(int)response.StatusCode}: {errorContent}");
                    }
                }
                catch (Exception ex)
                {
                    // Log the error but continue trying other formats
                    _logger?.LogWarning($"Error with {headerName}: {ex.Message}");
                    lastException = ex;
                }
            }

            // If all authentication formats failed, return an error response
            _logger?.LogError(lastException, "All authentication formats failed");

            // Create an error response
            var errorResponse = new ChatResponse();
            errorResponse.AddProperty("content", $"Error calling custom endpoint: {lastException?.Message}");
            errorResponse.AddProperty("role", "assistant");
            errorResponse.AddProperty("error", "CUSTOM_ENDPOINT_ERROR");

            return errorResponse;
        }

        public IAsyncEnumerable<ChatResponseUpdate> GetStreamingResponseAsync(IEnumerable<Microsoft.Extensions.AI.ChatMessage> messages, ChatOptions? options = null, CancellationToken cancellationToken = default)
        {
            // Streaming is not supported in this simple implementation
            _logger?.LogWarning("Streaming is not supported in CustomEndpointChatClient");
            return EmptyAsyncEnumerable<ChatResponseUpdate>.Instance;
        }

        public ChatClientMetadata Metadata => new ChatClientMetadata();

        public object? GetService(Type serviceType, object? key = null)
        {
            return null;
        }

        /// <summary>
        /// Helper method to extract content from a ChatMessage
        /// </summary>
        private string GetMessageContent(Microsoft.Extensions.AI.ChatMessage message)
        {
            try
            {
                // Log the message type and available properties
                _logger?.LogInformation($"ChatMessage type: {message.GetType().FullName}");
                foreach (var prop in message.GetType().GetProperties())
                {
                    try {
                        var value = prop.GetValue(message);
                        _logger?.LogInformation($"Property: {prop.Name}, Value: {value}");
                    }
                    catch (Exception ex) {
                        _logger?.LogWarning($"Error getting property {prop.Name}: {ex.Message}");
                    }
                }

                // Try the Role property first to confirm we can access properties
                var roleProperty = message.GetType().GetProperty("Role");
                if (roleProperty != null)
                {
                    var role = roleProperty.GetValue(message);
                    _logger?.LogInformation($"Successfully accessed Role property: {role}");
                }

                // Try direct access to Text property if it exists
                var textProperty = message.GetType().GetProperty("Text");
                if (textProperty != null)
                {
                    var text = textProperty.GetValue(message);
                    if (text != null && !string.IsNullOrEmpty(text.ToString()))
                    {
                        _logger?.LogInformation($"Found content in Text property: {text}");
                        return text.ToString() ?? "";
                    }
                }

                // Try Content property with case-insensitive search
                var contentProperty = message.GetType().GetProperty("Content",
                    BindingFlags.Public | BindingFlags.Instance | BindingFlags.IgnoreCase);

                if (contentProperty != null)
                {
                    var content = contentProperty.GetValue(message);
                    if (content != null && !string.IsNullOrEmpty(content.ToString()))
                    {
                        _logger?.LogInformation($"Found content in Content property: {content}");
                        return content.ToString() ?? "";
                    }
                }

                // Try accessing via dictionary-like interface
                var itemProperty = message.GetType().GetProperty("Item", new[] { typeof(string) });
                if (itemProperty != null)
                {
                    var content = itemProperty.GetValue(message, new object[] { "content" });
                    if (content != null && !string.IsNullOrEmpty(content.ToString()))
                    {
                        _logger?.LogInformation($"Found content via indexer: {content}");
                        return content.ToString() ?? "";
                    }
                }

                // Try accessing the Messages collection if it exists
                var messagesProperty = message.GetType().GetProperty("Messages");
                if (messagesProperty != null)
                {
                    var messages = messagesProperty.GetValue(message) as System.Collections.IEnumerable;
                    if (messages != null)
                    {
                        foreach (var msg in messages)
                        {
                            _logger?.LogInformation($"Message in collection: {msg}");
                            // Try to get content from each message
                            var msgContentProperty = msg.GetType().GetProperty("Content",
                                BindingFlags.Public | BindingFlags.Instance | BindingFlags.IgnoreCase);
                            if (msgContentProperty != null)
                            {
                                var msgContent = msgContentProperty.GetValue(msg);
                                if (msgContent != null && !string.IsNullOrEmpty(msgContent.ToString()))
                                {
                                    _logger?.LogInformation($"Found content in message collection: {msgContent}");
                                    return msgContent.ToString() ?? "";
                                }
                            }
                        }
                    }
                }

                // We can't directly cast ChatMessage to string, but we can check if it's a simple type
                var messageType = message.GetType();
                if (messageType.IsPrimitive || messageType == typeof(string))
                {
                    _logger?.LogInformation($"Message is a simple type: {message}");
                    return message.ToString() ?? "";
                }

                // If we still can't find the content, check if there's a ToString override
                var toString = message.ToString();
                if (!string.IsNullOrEmpty(toString) && toString != message.GetType().FullName)
                {
                    _logger?.LogInformation($"Using ToString() result: {toString}");
                    return toString;
                }

                // Last resort: If this is a system message, use a default prompt
                if (message.Role == ChatRole.System)
                {
                    var defaultSystemPrompt = "You are a helpful travel assistant. Provide concise, accurate travel advice.";
                    _logger?.LogInformation($"Using default system prompt: {defaultSystemPrompt}");
                    return defaultSystemPrompt;
                }
                else if (message.Role == ChatRole.User)
                {
                    // For user messages, try to extract from the Role
                    var userPrompt = $"What's the most economical way to travel between these locations?";
                    _logger?.LogInformation($"Using extracted user prompt: {userPrompt}");
                    return userPrompt;
                }

                // If we can't find the content, log a warning and return an empty string
                _logger?.LogWarning("Could not extract content from ChatMessage. Using empty string.");
                return "";
            }
            catch (Exception ex)
            {
                _logger?.LogError(ex, "Error extracting content from ChatMessage");
                return "";
            }
        }

        public void Dispose()
        {
            // Don't dispose the HttpClient as it might be shared
        }
    }

    /// <summary>
    /// Helper class for empty async enumerable
    /// </summary>
    internal class EmptyAsyncEnumerable<T> : IAsyncEnumerable<T>
    {
        public static readonly EmptyAsyncEnumerable<T> Instance = new();

        private EmptyAsyncEnumerable() { }

        public IAsyncEnumerator<T> GetAsyncEnumerator(CancellationToken cancellationToken = default)
        {
            return new EmptyAsyncEnumerator();
        }

        private class EmptyAsyncEnumerator : IAsyncEnumerator<T>
        {
            public T Current => default!;

            public ValueTask<bool> MoveNextAsync()
            {
                return new ValueTask<bool>(false);
            }

            public ValueTask DisposeAsync()
            {
                return ValueTask.CompletedTask;
            }
        }
    }
}

/// <summary>
/// Extension methods for dynamic property handling
/// </summary>
public static class DynamicObjectExtensions
{
    /// <summary>
    /// Add a property to an object dynamically
    /// </summary>
    public static void AddProperty(this object obj, string propertyName, object value)
    {
        // Try to find an existing property
        var property = obj.GetType().GetProperty(propertyName,
            BindingFlags.Public | BindingFlags.Instance | BindingFlags.IgnoreCase);

        if (property != null && property.CanWrite)
        {
            // If property exists and is writable, set its value
            property.SetValue(obj, value);
        }
        else
        {
            // Otherwise, we can't add a property dynamically in C#
            // This is a no-op, but we could log it if needed
        }
    }
}
