using System;
using System.Linq;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Logging;
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
                var serviceName = configuration["GenAI:ServiceName"] ?? "travel-advisor-llm";

                // Try to get GenAI credentials from bound services
                if (TryGetServiceCredentials(configuration, serviceName, out var credentials))
                {
                    options.ApiKey = credentials.GetValue<string>("api_key") ??
                                    credentials.GetValue<string>("apiKey") ??
                                    configuration["GenAI:ApiKey"] ?? "";

                    options.ApiUrl = credentials.GetValue<string>("api_url") ??
                                    credentials.GetValue<string>("apiUrl") ??
                                    configuration["GenAI:ApiUrl"] ?? "";

                    options.Model = credentials.GetValue<string>("model") ??
                                    configuration["GenAI:Model"] ?? "";
                }
                else
                {
                    // Fallback to configuration
                    options.ApiKey = configuration["GenAI:ApiKey"] ?? "";
                    options.ApiUrl = configuration["GenAI:ApiUrl"] ?? "";
                    options.Model = configuration["GenAI:Model"] ?? "";
                }

                options.ServiceName = serviceName;
            });

            // Configure Google Maps service
            services.Configure<GoogleMapsOptions>(options =>
            {
                options.ApiKey = configuration["GoogleMaps:ApiKey"] ?? "";
            });

            return services;
        }

        /// <summary>
        /// Try to get service credentials from bound Cloud Foundry services
        /// </summary>
        private static bool TryGetServiceCredentials(IConfiguration configuration, string serviceName, out IConfigurationSection credentials)
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
                    var name = serviceInstance.GetValue<string>("name");

                    // Check if this is the service we're looking for
                    if (name == serviceName)
                    {
                        credentials = serviceInstance.GetSection("credentials");
                        return credentials.Exists();
                    }

                    // If not found by name, check if it has "genai" in name or tags
                    if (name != null && name.Contains("genai", StringComparison.OrdinalIgnoreCase))
                    {
                        credentials = serviceInstance.GetSection("credentials");
                        return credentials.Exists();
                    }

                    // Check tags if present
                    var tagsSection = serviceInstance.GetSection("tags");
                    if (tagsSection.Exists())
                    {
                        var tags = tagsSection.GetChildren().Select(tag => tag.Value);
                        if (tags.Any(tag => tag != null && tag.Contains("genai", StringComparison.OrdinalIgnoreCase)))
                        {
                            credentials = serviceInstance.GetSection("credentials");
                            return credentials.Exists();
                        }
                    }
                }
            }

            return false;
        }
    }
}
