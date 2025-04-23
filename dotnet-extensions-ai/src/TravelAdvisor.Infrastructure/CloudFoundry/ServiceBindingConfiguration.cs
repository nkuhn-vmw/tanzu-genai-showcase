using System;
using System.Collections.Generic;
using System.Linq;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Options;
using Steeltoe.Connector.CloudFoundry;
using Steeltoe.Extensions.Configuration.CloudFoundry;
using TravelAdvisor.Infrastructure.Options;

namespace TravelAdvisor.Infrastructure.CloudFoundry
{
    /// <summary>
    /// Configuration for Cloud Foundry service bindings
    /// </summary>
    public static class ServiceBindingConfiguration
    {
        /// <summary>
        /// Adds Cloud Foundry service binding configurations to the service collection
        /// </summary>
        public static IServiceCollection AddCloudFoundryServices(this IServiceCollection services, IConfiguration configuration)
        {
            // Add Cloud Foundry options
            services.AddOptions();
            services.ConfigureCloudFoundryOptions(configuration);

            // Configure GenAI service
            services.Configure<GenAIOptions>(options =>
            {
                if (TryGetGenAIChatServiceCredentials(configuration, out var credentials))
                {
                    // Map the credentials to the same properties as the Java implementation
                    options.ApiKey = credentials.GetValue<string>("api_key") ?? "";
                    options.ApiUrl = credentials.GetValue<string>("api_base") ?? "";
                    options.Model = credentials.GetValue<string>("model_name") ?? "";
                    options.ServiceName = "GenAI on Tanzu Platform (chat)";
                }
                else
                {
                    // Fallback to configuration
                    options.ApiKey = configuration["GenAI:ApiKey"] ?? "";
                    options.ApiUrl = configuration["GenAI:ApiUrl"] ?? "";
                    options.Model = configuration["GenAI:Model"] ?? "";
                    options.ServiceName = configuration["GenAI:ServiceName"] ?? "travel-advisor-llm";
                }
            });

            // Configure Google Maps service (unchanged)
            services.Configure<GoogleMapsOptions>(options =>
            {
                options.ApiKey = configuration["GoogleMaps:ApiKey"] ?? "";
            });

            return services;
        }

        /// <summary>
        /// Try to get GenAI Chat service credentials from bound Cloud Foundry services
        /// Following the Java implementation pattern
        /// </summary>
        private static bool TryGetGenAIChatServiceCredentials(IConfiguration configuration, out IConfigurationSection credentials)
        {
            // Default credentials section
            credentials = configuration.GetSection("GenAI");

            // Check if running on Cloud Foundry by looking for VCAP_* sections
            var vcapSection = configuration.GetSection("vcap");
            if (!vcapSection.Exists())
            {
                return false;
            }

            // Look for service bindings in VCAP_SERVICES
            var servicesSection = configuration.GetSection("vcap:services");
            if (!servicesSection.Exists())
            {
                return false;
            }

            // Check each service type
            foreach (var serviceType in servicesSection.GetChildren())
            {
                // Check each service instance within the type
                foreach (var serviceInstance in serviceType.GetChildren())
                {
                    bool hasGenAITag = false;
                    bool hasGenAILabel = false;

                    // Check if service has genai tag
                    var tagsSection = serviceInstance.GetSection("tags");
                    if (tagsSection.Exists())
                    {
                        var tags = tagsSection.GetChildren().Select(tag => tag.Value);
                        hasGenAITag = tags.Any(tag => tag != null &&
                                         tag.Contains("genai", StringComparison.OrdinalIgnoreCase));
                    }

                    // Check if service has label starting with genai
                    var labelSection = serviceInstance.GetSection("label");
                    if (labelSection.Exists())
                    {
                        var label = labelSection.Value;
                        hasGenAILabel = label != null &&
                                      label.StartsWith("genai", StringComparison.OrdinalIgnoreCase);
                    }

                    // If this is a GenAI service, check if it supports chat
                    if (hasGenAITag || hasGenAILabel)
                    {
                        var credentialsSection = serviceInstance.GetSection("credentials");
                        if (credentialsSection.Exists())
                        {
                            // Check if model_capabilities contains "chat"
                            var modelCapabilitiesSection = credentialsSection.GetSection("model_capabilities");
                            if (modelCapabilitiesSection.Exists())
                            {
                                var modelCapabilities = modelCapabilitiesSection.GetChildren().Select(c => c.Value);
                                if (modelCapabilities.Any(cap => cap != null &&
                                                     cap.Equals("chat", StringComparison.OrdinalIgnoreCase)))
                                {
                                    credentials = credentialsSection;
                                    return true;
                                }
                            }
                        }
                    }
                }
            }

            return false;
        }
    }
}