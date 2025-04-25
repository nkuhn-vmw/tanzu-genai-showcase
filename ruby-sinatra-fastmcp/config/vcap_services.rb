require 'json'

module VcapServices
  # Environment Variable Fallbacks:
  # The service will check for credentials in this order:
  # 1. Cloud Foundry VCAP_SERVICES (GenAI service binding)
  # 2. Environment variables (multiple options for compatibility):
  #    - API Key: LLM_API_KEY, API_KEY, GENAI_API_KEY
  #    - API Base URL: LLM_API_BASE, API_BASE_URL, GENAI_API_BASE_URL
  #    - Model: LLM_MODEL, MODEL_NAME, GENAI_MODEL
  # 3. Default values where appropriate

  # Parses VCAP_SERVICES environment variable in Cloud Foundry
  # and returns service credentials
  def self.parse_service_credentials(service_name)
    return nil unless ENV['VCAP_SERVICES']

    begin
      vcap_services = JSON.parse(ENV['VCAP_SERVICES'])

      # Find the service by name (matches service_name partially)
      service_key = vcap_services.keys.find do |key|
        key.downcase.include?(service_name.downcase)
      end

      return nil unless service_key

      # Return the credentials from the first instance
      vcap_services[service_key].first['credentials']
    rescue JSON::ParserError => e
      puts "Error parsing VCAP_SERVICES: #{e.message}"
      nil
    end
  end

  # Check if running in Cloud Foundry
  def self.cloud_foundry?
    !ENV['VCAP_APPLICATION'].nil?
  end

  # Get GenAI LLM service credentials with enhanced detection
  def self.parse_genai_credentials
    return nil unless ENV['VCAP_SERVICES']

    begin
      vcap_services = JSON.parse(ENV['VCAP_SERVICES'])

      # Iterate through all services to find GenAI services
      vcap_services.each do |service_name, instances|
        instances.each do |instance|
          # Check for genai tag
          has_genai_tag = instance['tags']&.any? { |tag| tag.to_s.downcase.include?('genai') }

          # Check for genai label
          has_genai_label = instance['label']&.downcase&.include?('genai')

          # Check service name
          has_genai_name = service_name.downcase.include?('genai') ||
                           service_name.downcase.include?('llm')

          if has_genai_tag || has_genai_label || has_genai_name
            # Found a potential GenAI service, check for chat capability
            credentials = instance['credentials']
            next unless credentials

            # Check for model_capabilities
            model_capabilities = credentials['model_capabilities']
            has_chat_capability = model_capabilities&.any? { |cap| cap.to_s.downcase == 'chat' }

            # If no capabilities specified or has chat capability
            if !model_capabilities || has_chat_capability
              # Extract credentials with proper field mapping
              result = {}

              # API Key
              result['api_key'] = credentials['api_key'] || credentials['apiKey']

              # API Base URL
              result['api_base'] = credentials['api_base'] ||
                                  credentials['url'] ||
                                  credentials['baseUrl'] ||
                                  credentials['base_url']

              # Model Name
              model_name = credentials['model_name'] || credentials['model']

              # If model_provider is available, prefix the model name
              if credentials['model_provider'] && model_name
                result['model'] = "#{credentials['model_provider']}/#{model_name}"
              else
                result['model'] = model_name
              end

              return result
            end
          end
        end
      end

      nil
    rescue JSON::ParserError => e
      puts "Error parsing VCAP_SERVICES: #{e.message}"
      nil
    end
  end

  # Get GenAI LLM service credentials if available
  def self.genai_llm_credentials
    parse_genai_credentials || parse_service_credentials('genai')
  end

  # Get API key from service bindings or environment variable
  def self.get_api_key(env_var_name)
    if cloud_foundry?
      # Try to get from service bindings
      credentials = genai_llm_credentials
      return credentials['api_key'] if credentials && credentials['api_key']
    end

    # Fall back to environment variable with multiple options
    ENV[env_var_name] || ENV['LLM_API_KEY'] || ENV['API_KEY'] || ENV['GENAI_API_KEY']
  end

  # Get complete LLM configuration (api_key, api_base, model)
  def self.get_llm_config
    if cloud_foundry?
      # Try to get from service bindings
      credentials = genai_llm_credentials
      if credentials
        return {
          api_key: credentials['api_key'],
          api_base: credentials['api_base'],
          model: credentials['model']
        }.compact
      end
    end

    # Fall back to environment variables
    {
      api_key: ENV['LLM_API_KEY'] || ENV['API_KEY'] || ENV['GENAI_API_KEY'],
      api_base: ENV['LLM_API_BASE'] || ENV['API_BASE_URL'] || ENV['GENAI_API_BASE_URL'],
      model: ENV['LLM_MODEL'] || ENV['MODEL_NAME'] || ENV['GENAI_MODEL'] || 'gpt-4o-mini'
    }.compact
  end
end
