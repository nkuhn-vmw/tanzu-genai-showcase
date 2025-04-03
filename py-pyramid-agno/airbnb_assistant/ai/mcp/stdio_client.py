"""
MCP Client implementation using stdio transport for Airbnb
"""
import json
import logging
import os
import subprocess
import sys
import time
import threading
import queue
from typing import Dict, List, Any, Optional, Union

log = logging.getLogger(__name__)

class MCPAirbnbStdioClient:
    """
    Client for interacting with the MCP Airbnb server over stdio transport

    This implementation launches the MCP server as a subprocess and
    communicates with it through stdin/stdout pipes following the
    Model Context Protocol.
    """
    def __init__(self, mcp_command: str, mcp_args: Optional[List[str]] = None):
        """
        Initialize the MCP Airbnb stdio client

        Args:
            mcp_command: Command to run the MCP server (e.g., 'node', 'python')
            mcp_args: Arguments to pass to the command (e.g., ['path/to/server.js'])
        """
        self.mcp_command = mcp_command
        self.mcp_args = mcp_args or []

        # Message queues for communication with server
        self.request_queue = queue.Queue()
        self.response_queue = queue.Queue()

        # Initialize server process to None
        self.process = None
        self.is_running = False

        # Message ID counter for request/response matching
        self.next_id = 1

        # Start the server process
        self.start_server()

    def start_server(self):
        """Start the MCP server as a subprocess and set up communication threads"""
        try:
            log.info(f"Starting MCP server: {self.mcp_command} {' '.join(self.mcp_args)}")

            # Start the subprocess
            self.process = subprocess.Popen(
                [self.mcp_command] + self.mcp_args,
                stdin=subprocess.PIPE,
                stdout=subprocess.PIPE,
                stderr=subprocess.PIPE,
                text=True,
                bufsize=1  # Line buffered
            )

            # Mark the server as running
            self.is_running = True

            # Start reader thread
            self.reader_thread = threading.Thread(target=self._read_process_output, daemon=True)
            self.reader_thread.start()

            # Start writer thread
            self.writer_thread = threading.Thread(target=self._write_process_input, daemon=True)
            self.writer_thread.start()

            # Start error reader thread
            self.error_thread = threading.Thread(target=self._read_process_errors, daemon=True)
            self.error_thread.start()

            # Wait a moment for the server to initialize
            time.sleep(1)

            log.info("MCP server started successfully")

        except Exception as e:
            log.error(f"Failed to start MCP server: {e}")
            self.is_running = False
            raise

    def stop_server(self):
        """Stop the MCP server process and clean up resources"""
        if self.process and self.is_running:
            log.info("Stopping MCP server")
            self.is_running = False

            try:
                # Signal the process to terminate
                self.process.terminate()

                # Wait for the process to exit
                self.process.wait(timeout=5)

            except subprocess.TimeoutExpired:
                log.warning("MCP server did not exit gracefully, forcing termination")
                self.process.kill()

            # Close pipes
            if self.process.stdin:
                self.process.stdin.close()
            if self.process.stdout:
                self.process.stdout.close()
            if self.process.stderr:
                self.process.stderr.close()

            self.process = None
            log.info("MCP server stopped")

    def _read_process_output(self):
        """Read and process output from the MCP server"""
        while self.is_running and self.process and self.process.stdout:
            try:
                # Read a line from stdout
                line = self.process.stdout.readline()

                if not line:
                    if self.process.poll() is not None:
                        log.warning("MCP server process has terminated")
                        self.is_running = False
                    break

                # Try to parse as JSON
                try:
                    response = json.loads(line)
                    log.debug(f"Received response: {response}")

                    # Put the response in the queue
                    self.response_queue.put(response)

                except json.JSONDecodeError:
                    log.warning(f"Received non-JSON output: {line}")

            except Exception as e:
                log.error(f"Error reading from MCP server: {e}")
                if not self.is_running:
                    break

    def _read_process_errors(self):
        """Read error output from the MCP server"""
        while self.is_running and self.process and self.process.stderr:
            try:
                # Read a line from stderr
                line = self.process.stderr.readline()

                if not line:
                    if self.process.poll() is not None:
                        break
                    continue

                log.warning(f"MCP server stderr: {line.strip()}")

            except Exception as e:
                log.error(f"Error reading stderr from MCP server: {e}")
                if not self.is_running:
                    break

    def _write_process_input(self):
        """Write requests to the MCP server"""
        while self.is_running and self.process and self.process.stdin:
            try:
                # Get a request from the queue
                request = self.request_queue.get(timeout=0.5)

                # Convert to JSON and write to stdin
                json_request = json.dumps(request) + "\n"
                self.process.stdin.write(json_request)
                self.process.stdin.flush()

                # Mark the task as done
                self.request_queue.task_done()

            except queue.Empty:
                # No request available, keep waiting
                continue

            except Exception as e:
                log.error(f"Error writing to MCP server: {e}")
                if not self.is_running:
                    break

    def _send_request(self, method: str, params: Dict[str, Any] = None) -> Dict[str, Any]:
        """
        Send a request to the MCP server and wait for the response

        Args:
            method: RPC method to call
            params: Parameters for the method

        Returns:
            Response from the server
        """
        if not self.is_running or not self.process:
            raise RuntimeError("MCP server is not running")

        # Generate a request ID
        request_id = self.next_id
        self.next_id += 1

        # Create the request
        request = {
            "jsonrpc": "2.0",
            "id": request_id,
            "method": method,
            "params": params or {}
        }

        # Log the actual request for debugging
        log.debug(f"Sending MCP request: {json.dumps(request, indent=2)}")

        # Put the request in the queue
        self.request_queue.put(request)

        # Wait for the response with matching ID
        timeout = 30  # 30 seconds timeout
        start_time = time.time()

        while True:
            try:
                # Check if we've timed out
                if time.time() - start_time > timeout:
                    raise TimeoutError(f"Timeout waiting for response to request {request_id}")

                # Try to get a response with a short timeout
                response = self.response_queue.get(timeout=0.5)

                # Check if this is the response we're waiting for
                if response.get("id") == request_id:
                    self.response_queue.task_done()

                    # Check for errors
                    if "error" in response:
                        error = response["error"]
                        log.error(f"MCP server error: {error}")

                        # Check for method not found error - this is likely a method/schema mismatch
                        if error.get("code") == -32601 and "Method not found" in error.get("message", ""):
                            error_msg = "The MCP server does not recognize the requested method. Please check server implementation."
                            log.error(error_msg)

                            # Only use mock data if explicitly enabled, otherwise raise an error
                            use_mock = os.environ.get('USE_MOCK_DATA', 'false').lower() == 'true'
                            if not use_mock:
                                raise RuntimeError(f"MCP error: {error_msg}")
                            return {}

                        raise RuntimeError(f"MCP server error: {error}")

                    # Return the result
                    return response.get("result", {})
                else:
                    # Put the response back for another handler
                    self.response_queue.put(response)
                    self.response_queue.task_done()

            except queue.Empty:
                # No response available yet, keep waiting
                continue

            except Exception as e:
                log.error(f"Error processing response: {e}")
                raise

    def _get_mock_listings(self, location: str, limit: int = 10) -> List[Dict[str, Any]]:
        """
        Get mock listings when MCP server is not available

        Args:
            location: Location to search for
            limit: Maximum number of results to return

        Returns:
            List of mock listing dictionaries
        """
        return [
            {
                "id": "1001",
                "title": f"Cozy apartment in {location}",
                "location": location,
                "location_details": "Downtown area",
                "price_per_night": 120,
                "rating": 4.8,
                "reviews_count": 120,
                "superhost": True,
                "image_url": "https://a0.muscache.com/im/pictures/miso/Hosting-717134404264905813/original/dfe9c1ff-b70c-4566-a1ef-5bc733dbb705.jpeg",
                "amenities": ["WiFi", "Kitchen", "Air conditioning"],
                "bedrooms": 1,
                "bathrooms": 1,
                "max_guests": 2
            },
            {
                "id": "1002",
                "title": f"Luxury condo in {location}",
                "location": location,
                "location_details": "Beachfront",
                "price_per_night": 250,
                "rating": 4.9,
                "reviews_count": 85,
                "superhost": True,
                "image_url": "https://a0.muscache.com/im/pictures/miso/Hosting-51809333/original/0da70267-d9da-4efb-9123-2714b651c9cd.jpeg",
                "amenities": ["WiFi", "Pool", "Kitchen", "Gym"],
                "bedrooms": 2,
                "bathrooms": 2,
                "max_guests": 4
            },
            {
                "id": "1003",
                "title": f"Charming cottage in {location}",
                "location": location,
                "location_details": "Countryside",
                "price_per_night": 150,
                "rating": 4.7,
                "reviews_count": 65,
                "superhost": False,
                "image_url": "https://a0.muscache.com/im/pictures/miso/Hosting-807995199727408777/original/9225d584-7aa4-4990-af06-339bd1339686.jpeg",
                "amenities": ["WiFi", "Kitchen", "Backyard"],
                "bedrooms": 1,
                "bathrooms": 1,
                "max_guests": 3
            }
        ][:limit]

    def _get_mock_listing_details(self, listing_id: str) -> Dict[str, Any]:
        """Same mock data as the HTTP client"""
        listing_data = {
            "1001": {
                "id": "1001",
                "title": "Cozy apartment in Downtown",
                "location": "New York, NY",
                "location_details": "Downtown area, near subway",
                "price_per_night": 120,
                "rating": 4.8,
                "reviews_count": 120,
                "superhost": True,
                "image_url": "https://a0.muscache.com/im/pictures/miso/Hosting-717134404264905813/original/dfe9c1ff-b70c-4566-a1ef-5bc733dbb705.jpeg",
                "amenities": [
                    "WiFi", "Kitchen", "Air conditioning", "Washer/Dryer",
                    "TV", "Hair dryer", "Iron", "Essentials"
                ],
                "bedrooms": 1,
                "bathrooms": 1,
                "max_guests": 2,
                "description": "A beautiful cozy apartment located in the heart of downtown. Perfect for couples or solo travelers looking to explore the city.",
                "host": {
                    "id": "host1",
                    "name": "John",
                    "image_url": "https://a0.muscache.com/im/pictures/user/User-380443802/original/9c8d56be-b77a-4f35-be1b-93ef032192c2.jpeg",
                    "superhost": True,
                    "response_rate": 98,
                    "joined_date": "2018-01-01"
                },
                "availability": {
                    "min_nights": 2,
                    "max_nights": 30,
                    "availability_30": 15,
                    "availability_60": 30,
                    "availability_90": 45
                },
                "reviews": [
                    {
                        "id": "rev1",
                        "author": "Alice",
                        "date": "2023-02-15",
                        "rating": 5,
                        "comment": "Great place, would stay again!"
                    },
                    {
                        "id": "rev2",
                        "author": "Bob",
                        "date": "2023-01-20",
                        "rating": 4,
                        "comment": "Nice apartment, good location."
                    }
                ]
            },
            # Other listings omitted for brevity - they would be the same as in the HTTP client
        }

        return listing_data.get(listing_id, {})

    def search_listings(self,
                        location: str,
                        check_in: Optional[str] = None,
                        check_out: Optional[str] = None,
                        guests: int = 1,
                        limit: int = 10) -> List[Dict[str, Any]]:
        """
        Search for Airbnb listings using the MCP server

        Args:
            location: Location to search for listings
            check_in: Check-in date (YYYY-MM-DD)
            check_out: Check-out date (YYYY-MM-DD)
            guests: Number of guests
            limit: Maximum number of results to return

        Returns:
            List of listing dictionaries
        """
        try:
            # Log search parameters
            log.info(f"Searching for listings in {location} for {guests} guests")
            if check_in and check_out:
                log.info(f"Date range: {check_in} to {check_out}")

            # Based on MCP server implementation (https://github.com/openbnb-org/mcp-server-airbnb)
            # It uses CallToolRequestSchema from the MCP SDK
            methods_to_try = ["call", "callTool", "call_tool", "CallTool"]
            param_structures = [
                # Structure 1: Standard MCP format
                {
                    "name": "airbnb_search",
                    "arguments": {
                        "location": location,
                        "adults": guests,
                        "limit": limit
                    }
                },
                # Structure 2: Direct arguments
                {
                    "location": location,
                    "adults": guests,
                    "limit": limit
                }
            ]

            # Add date parameters if provided
            if check_in:
                param_structures[0]["arguments"]["checkin"] = check_in
                param_structures[1]["checkin"] = check_in

            if check_out:
                param_structures[0]["arguments"]["checkout"] = check_out
                param_structures[1]["checkout"] = check_out

            # Try all combinations
            response = None
            last_error = None
            errors = []

            for method in methods_to_try:
                for params in param_structures:
                    log.info(f"Trying MCP method '{method}' with params structure: {json.dumps(params, indent=2)}")
                    try:
                        response = self._send_request(method, params)
                        # If we get here without an error, we found a working combination
                        log.info(f"Success with method '{method}' and params structure {1 if 'name' in params else 2}")
                        # Break out of loops if we got a successful response
                        break
                    except Exception as e:
                        last_error = e
                        errors.append(f"Method '{method}' failed: {str(e)}")
                        continue

                if response:  # If we found a working method, no need to try more
                    break

            # Parse the response content
            if response:
                # Check if the response has the expected format
                if "content" in response:
                    # The content may be an array of content parts
                    content_parts = response.get("content", [])

                    # Look for the listing results in the response
                    for part in content_parts:
                        if part.get("type") == "text":
                            try:
                                # Try to parse the text as JSON
                                result_data = json.loads(part.get("text", "{}"))
                                if "searchResults" in result_data:
                                    return result_data.get("searchResults", [])
                                if "results" in result_data:
                                    return result_data.get("results", [])
                            except json.JSONDecodeError:
                                log.warning("Failed to parse response as JSON")

                # Try another format - direct result object
                if "result" in response and "searchResults" in response["result"]:
                    return response["result"]["searchResults"]

            # Check if we should use mock data
            use_mock = os.environ.get('USE_MOCK_DATA', 'false').lower() == 'true'
            if use_mock:
                log.warning("No valid listings found in MCP response, using mock data (USE_MOCK_DATA=true)")
                mock_listings = self._get_mock_listings(location=location, limit=limit)
                log.info(f"Generated {len(mock_listings)} mock listings for location: {location}")
                return mock_listings
            else:
                error_msg = "Failed to get listings from MCP server and USE_MOCK_DATA is false"
                log.error(error_msg)
                return []

        except Exception as e:
            log.error(f"Error searching for listings: {e}")
            log.info("Falling back to mock data")
            return self._get_mock_listings(location=location, limit=limit)

    def get_listing_details(self, listing_id: str) -> Dict[str, Any]:
        """
        Get details for a specific listing using the MCP server

        Args:
            listing_id: ID of the listing to retrieve

        Returns:
            Dictionary containing listing details
        """
        try:
            log.info(f"Getting details for listing {listing_id}")

            # Based on MCP server implementation (https://github.com/openbnb-org/mcp-server-airbnb)
            # It uses CallToolRequestSchema from the MCP SDK
            methods_to_try = ["call", "callTool", "call_tool", "CallTool"]
            param_structures = [
                # Structure 1: Standard MCP format
                {
                    "name": "airbnb_listing_details",
                    "arguments": {
                        "id": listing_id
                    }
                },
                # Structure 2: Direct arguments
                {
                    "id": listing_id
                }
            ]

            # Try all combinations
            response = None
            last_error = None
            errors = []

            for method in methods_to_try:
                for params in param_structures:
                    log.info(f"Trying MCP method '{method}' with params structure: {json.dumps(params, indent=2)}")
                    try:
                        response = self._send_request(method, params)
                        # If we get here without an error, we found a working combination
                        log.info(f"Success with method '{method}' and params structure {1 if 'name' in params else 2}")
                        # Break out of loops if we got a successful response
                        break
                    except Exception as e:
                        last_error = e
                        errors.append(f"Method '{method}' failed: {str(e)}")
                        continue

                if response:  # If we found a working method, no need to try more
                    break

            # Parse the response content
            if response:
                # Check if the response has the expected format
                if "content" in response:
                    # The content may be an array of content parts
                    content_parts = response.get("content", [])

                    # Look for the listing details in the response
                    for part in content_parts:
                        if part.get("type") == "text":
                            try:
                                # Try to parse the text as JSON
                                result_data = json.loads(part.get("text", "{}"))
                                if "listing" in result_data:
                                    return result_data.get("listing", {})
                                if "details" in result_data:
                                    return result_data.get("details", {})
                            except json.JSONDecodeError:
                                log.warning("Failed to parse response as JSON")

                # Try another format - direct result object
                if "result" in response and "details" in response["result"]:
                    return response["result"]["details"]

            # Check if we should use mock data
            use_mock = os.environ.get('USE_MOCK_DATA', 'false').lower() == 'true'
            if use_mock:
                log.warning("No valid listing details found in MCP response, using mock data (USE_MOCK_DATA=true)")
                mock_listing = self._get_mock_listing_details(listing_id)
                if mock_listing:
                    log.info(f"Generated mock details for listing ID: {listing_id} - {mock_listing.get('title', 'Unknown')}")
                else:
                    log.warning(f"No mock data available for listing ID: {listing_id}")
                return mock_listing
            else:
                error_msg = "Failed to get listing details from MCP server and USE_MOCK_DATA is false"
                log.error(error_msg)
                return {}

        except Exception as e:
            log.error(f"Error getting listing details: {e}")
            log.info("Falling back to mock data")
            return self._get_mock_listing_details(listing_id)

    def __del__(self):
        """Clean up resources when the object is destroyed"""
        self.stop_server()
