"""
MCP Client implementation for Airbnb
"""
import json
import logging
import os
import requests
import datetime
from typing import Dict, List, Any, Optional, Union

log = logging.getLogger(__name__)

class MCPAirbnbClient:
    """
    Client for interacting with the MCP Airbnb server

    This implementation follows the Model Context Protocol (MCP) patterns
    to provide external data and actions to the Agno agent.
    """
    def __init__(self, mcp_url: Optional[str] = None):
        """
        Initialize the MCP Airbnb client

        Args:
            mcp_url: URL of the MCP server (defaults to environment variable or localhost)
        """
        self.mcp_url = mcp_url or os.environ.get('MCP_AIRBNB_URL', 'http://localhost:3000')
        log.info(f"Initializing MCP client with URL: {self.mcp_url}")
        self.session = requests.Session()

        # Check for mock mode environment variable
        self.use_mock = os.environ.get('USE_MOCK_DATA', 'false').lower() == 'true'
        if self.use_mock:
            log.info("MCP client running in mock mode")

    def _get_mock_listings(self, location: str, limit: int = 10) -> List[Dict[str, Any]]:
        """
        Get mock listings for development when MCP server is not available

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
        """
        Get mock listing details for development when MCP server is not available

        Args:
            listing_id: ID of the listing to retrieve

        Returns:
            Dictionary containing mock listing details
        """
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
            "1002": {
                "id": "1002",
                "title": "Luxury condo with ocean view",
                "location": "Miami, FL",
                "location_details": "Beachfront property with direct beach access",
                "price_per_night": 250,
                "rating": 4.9,
                "reviews_count": 85,
                "superhost": True,
                "image_url": "https://a0.muscache.com/im/pictures/miso/Hosting-51809333/original/0da70267-d9da-4efb-9123-2714b651c9cd.jpeg",
                "amenities": [
                    "WiFi", "Pool", "Kitchen", "Gym", "Air conditioning",
                    "Washer/Dryer", "TV", "Hair dryer", "Iron", "Essentials",
                    "Parking", "Hot tub", "Beach access"
                ],
                "bedrooms": 2,
                "bathrooms": 2,
                "max_guests": 4,
                "description": "A luxurious condo with breathtaking ocean views. Enjoy the sunset from your private balcony or take a dip in the infinity pool.",
                "host": {
                    "id": "host2",
                    "name": "Sarah",
                    "image_url": "https://a0.muscache.com/im/pictures/user/User-35458447/original/e9c212d2-aa25-4f5c-bbc1-65b521a92fa4.jpeg",
                    "superhost": True,
                    "response_rate": 100,
                    "joined_date": "2016-05-15"
                },
                "availability": {
                    "min_nights": 3,
                    "max_nights": 60,
                    "availability_30": 10,
                    "availability_60": 25,
                    "availability_90": 40
                },
                "reviews": [
                    {
                        "id": "rev3",
                        "author": "Charlie",
                        "date": "2023-03-10",
                        "rating": 5,
                        "comment": "Absolutely amazing! The view is stunning."
                    },
                    {
                        "id": "rev4",
                        "author": "Diana",
                        "date": "2023-02-28",
                        "rating": 5,
                        "comment": "Perfect location, beautiful condo, great host!"
                    }
                ]
            },
            "1003": {
                "id": "1003",
                "title": "Charming cottage in countryside",
                "location": "Vermont",
                "location_details": "Countryside, 10 minute drive to town",
                "price_per_night": 150,
                "rating": 4.7,
                "reviews_count": 65,
                "superhost": False,
                "image_url": "https://a0.muscache.com/im/pictures/miso/Hosting-807995199727408777/original/9225d584-7aa4-4990-af06-339bd1339686.jpeg",
                "amenities": [
                    "WiFi", "Kitchen", "Backyard", "Fireplace",
                    "TV", "BBQ grill", "Parking", "Essentials"
                ],
                "bedrooms": 1,
                "bathrooms": 1,
                "max_guests": 3,
                "description": "A charming cottage nestled in the beautiful countryside. Perfect for a peaceful getaway from city life.",
                "host": {
                    "id": "host3",
                    "name": "Michael",
                    "image_url": "https://a0.muscache.com/im/pictures/user/de3724d8-155c-4ce1-b480-7a8cd1c42211.jpg",
                    "superhost": False,
                    "response_rate": 95,
                    "joined_date": "2019-07-20"
                },
                "availability": {
                    "min_nights": 2,
                    "max_nights": 14,
                    "availability_30": 20,
                    "availability_60": 40,
                    "availability_90": 60
                },
                "reviews": [
                    {
                        "id": "rev5",
                        "author": "Emma",
                        "date": "2023-01-05",
                        "rating": 5,
                        "comment": "Such a peaceful place! Loved every minute."
                    },
                    {
                        "id": "rev6",
                        "author": "Frank",
                        "date": "2022-12-18",
                        "rating": 4,
                        "comment": "Great cottage, a bit hard to find at night."
                    }
                ]
            }
        }

        return listing_data.get(listing_id, {})

    def _handle_api_error(self, e: Exception, fallback_function, *args, **kwargs):
        """
        Handle API errors by logging and using fallback function

        Args:
            e: The exception that occurred
            fallback_function: Function to call as fallback
            *args, **kwargs: Arguments to pass to the fallback function

        Returns:
            Result from fallback function
        """
        log.error(f"Error calling MCP server: {e}")
        log.info("Falling back to mock data")
        return fallback_function(*args, **kwargs)

    def search_listings(self,
                        location: str,
                        check_in: Optional[str] = None,
                        check_out: Optional[str] = None,
                        guests: int = 1,
                        limit: int = 10) -> List[Dict[str, Any]]:
        """
        Search for Airbnb listings

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

            # Use mock data if requested or if in development mode
            if self.use_mock:
                log.info("Using mock data for listings")
                return self._get_mock_listings(location=location, limit=limit)

            # Prepare request parameters
            params = {
                "location": location,
                "guests": guests,
                "limit": limit
            }

            if check_in:
                params["check_in"] = check_in

            if check_out:
                params["check_out"] = check_out

            # Call the MCP server to search for listings
            try:
                # Based on MCP server implementation (https://github.com/openbnb-org/mcp-server-airbnb)
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

                response = None
                data = None
                last_error = None

                # Try all combinations of methods and parameter structures
                for method in methods_to_try:
                    for params_struct in param_structures:
                        try:
                            log.info(f"Trying MCP method '{method}' with params structure: {json.dumps(params_struct, indent=2)}")

                            # Create the JSON-RPC request
                            rpc_params = {
                                "jsonrpc": "2.0",
                                "id": 1,
                                "method": method,
                                "params": params_struct
                            }

                            # Log the actual request for debugging
                            log.debug(f"Sending MCP request: {json.dumps(rpc_params, indent=2)}")

                            response = self.session.post(f"{self.mcp_url}", json=rpc_params, timeout=10)
                            response.raise_for_status()
                            data = response.json()

                            # Check if response has an error
                            if "error" in data:
                                last_error = data["error"]
                                log.warning(f"Method {method} failed with error: {last_error}")
                                continue

                            # If we got here, the method worked
                            log.info(f"MCP method {method} succeeded")

                            # Parse and format the response
                            if "result" in data:
                                result = data["result"]
                                if "content" in result:
                                    for part in result["content"]:
                                        if part.get("type") == "text":
                                            try:
                                                result_data = json.loads(part.get("text", "{}"))
                                                if "searchResults" in result_data:
                                                    return result_data.get("searchResults", [])
                                            except json.JSONDecodeError:
                                                pass

                            # Exit the loops if we got a valid response
                            break
                        except Exception as inner_e:
                            log.warning(f"Method {method} attempt failed: {inner_e}")
                            last_error = inner_e
                            continue

                    # Break out of the outer loop if we got a valid response
                    if response is not None and response.status_code == 200 and data is not None and "error" not in data:
                        break

                # If we got a valid response but haven't returned yet, try to extract results
                if response is not None and data is not None and "error" not in data:
                    # Return the results or an empty list if no results are found
                    return data.get("results", [])

                # If we're here, all methods failed
                use_mock = os.environ.get('USE_MOCK_DATA', 'false').lower() == 'true'
                if use_mock:
                    log.warning("All method attempts failed, using mock data (USE_MOCK_DATA=true)")
                    return self._get_mock_listings(location=location, limit=limit)
                else:
                    error_msg = f"Failed to get listings from MCP server using any method and USE_MOCK_DATA is false: {last_error}"
                    log.error(error_msg)
                    return []

            except requests.exceptions.RequestException as e:
                use_mock = os.environ.get('USE_MOCK_DATA', 'false').lower() == 'true'
                if use_mock:
                    return self._handle_api_error(e, self._get_mock_listings, location=location, limit=limit)
                else:
                    error_msg = f"HTTP error communicating with MCP server and USE_MOCK_DATA is false: {e}"
                    log.error(error_msg)
                    return []

        except Exception as e:
            log.error(f"Error searching for listings: {e}")
            return []

    def get_listing_details(self, listing_id: str) -> Dict[str, Any]:
        """
        Get details for a specific listing

        Args:
            listing_id: ID of the listing to retrieve

        Returns:
            Dictionary containing listing details
        """
        try:
            log.info(f"Getting details for listing {listing_id}")

            # Use mock data if requested or if in development mode
            if self.use_mock:
                log.info("Using mock data for listing details")
                return self._get_mock_listing_details(listing_id)

            # Call the MCP server to get listing details
            try:
                # Based on MCP server implementation (https://github.com/openbnb-org/mcp-server-airbnb)
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

                response = None
                data = None
                last_error = None

                # Try all combinations
                for method in methods_to_try:
                    for params_struct in param_structures:
                        try:
                            log.info(f"Trying MCP method '{method}' with params structure: {json.dumps(params_struct, indent=2)}")

                            # Create the JSON-RPC request
                            rpc_params = {
                                "jsonrpc": "2.0",
                                "id": 1,
                                "method": method,
                                "params": params_struct
                            }

                            # Log the actual request for debugging
                            log.debug(f"Sending MCP request: {json.dumps(rpc_params, indent=2)}")

                            response = self.session.post(f"{self.mcp_url}", json=rpc_params, timeout=10)
                            response.raise_for_status()
                            data = response.json()

                            # Check if response has an error
                            if "error" in data:
                                last_error = data["error"]
                                log.warning(f"Method {method} failed with error: {last_error}")
                                continue

                            # If successful, exit the loops
                            log.info(f"Success with method '{method}' and param structure")
                            break
                        except Exception as e:
                            log.warning(f"Method {method} attempt failed: {e}")
                            last_error = e
                            continue

                    # Break out of the outer loop if we got a valid response
                    if response is not None and response.status_code == 200 and data is not None and "error" not in data:
                        break

                # If we got a valid response but haven't returned yet, try to extract results
                if response is not None and data is not None and "error" not in data:
                    # Check for different response formats
                    if "result" in data:
                        result = data["result"]
                        if "content" in result:
                            for part in result["content"]:
                                if part.get("type") == "text":
                                    try:
                                        result_data = json.loads(part.get("text", "{}"))
                                        if "listing" in result_data:
                                            return result_data.get("listing", {})
                                        if "details" in result_data:
                                            return result_data.get("details", {})
                                    except json.JSONDecodeError:
                                        pass

                        # Try direct result format
                        if "details" in result:
                            return result["details"]

                    # Return listing details if found in the data
                    if "listing" in data:
                        return data["listing"]

                    # Return empty dict if no listing details found
                    return {}

                # If we're here, all methods failed
                use_mock = os.environ.get('USE_MOCK_DATA', 'false').lower() == 'true'
                if use_mock:
                    log.warning("All method attempts failed, using mock data (USE_MOCK_DATA=true)")
                    return self._get_mock_listing_details(listing_id)
                else:
                    error_msg = f"Failed to get listing details from MCP server using any method and USE_MOCK_DATA is false: {last_error}"
                    log.error(error_msg)
                    return {}

            except requests.exceptions.RequestException as e:
                use_mock = os.environ.get('USE_MOCK_DATA', 'false').lower() == 'true'
                if use_mock:
                    return self._handle_api_error(e, self._get_mock_listing_details, listing_id)
                else:
                    error_msg = f"HTTP error communicating with MCP server and USE_MOCK_DATA is false: {e}"
                    log.error(error_msg)
                    return {}

        except Exception as e:
            log.error(f"Error getting listing details: {e}")
            return {}

