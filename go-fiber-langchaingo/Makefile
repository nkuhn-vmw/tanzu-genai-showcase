.PHONY: build run clean test cf-push cf-logs

# Default target
all: build

# Build the application
build:
	go build -o congress-chatbot cmd/server/main.go

# Run the application locally
run:
	go run cmd/server/main.go

# Clean build artifacts
clean:
	rm -f congress-chatbot

# Run tests
test:
	go test ./...

# Push to Cloud Foundry
cf-push:
	cf push

# View Cloud Foundry logs
cf-logs:
	cf logs congress-chatbot --recent
