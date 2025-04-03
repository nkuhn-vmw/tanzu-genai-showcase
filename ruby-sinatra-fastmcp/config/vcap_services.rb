require 'json'

module VcapServices
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
  
  # Get GenAI LLM service credentials if available
  def self.genai_llm_credentials
    parse_service_credentials('genai')
  end
  
  # Get API key from service bindings or environment variable
  def self.get_api_key(env_var_name)
    if cloud_foundry?
      # Try to get from service bindings
      credentials = genai_llm_credentials
      return credentials['api_key'] if credentials && credentials['api_key']
    end
    
    # Fall back to environment variable
    ENV[env_var_name]
  end
end
