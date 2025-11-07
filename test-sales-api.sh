#!/bin/bash

# Sales API Testing Script
# This script tests all the new endpoints implemented for the Sales Page

BASE_URL="http://localhost:8000/api/v1"
TOKEN="" # Set your auth token here

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "================================================"
echo "Sales API Testing Script"
echo "================================================"
echo ""

# Check if token is set
if [ -z "$TOKEN" ]; then
    echo -e "${RED}ERROR: Please set your auth token in the script${NC}"
    echo "Get token by logging in:"
    echo "curl -X POST $BASE_URL/../auth/login -H 'Content-Type: application/json' -d '{\"email\":\"your@email.com\",\"password\":\"password\"}'"
    exit 1
fi

HEADERS="-H 'Authorization: Bearer $TOKEN' -H 'Content-Type: application/json' -H 'Accept: application/json'"

# Test 1: Open Cash Session
echo -e "${YELLOW}Test 1: Open Cash Session${NC}"
echo "POST $BASE_URL/cash-sessions"
RESPONSE=$(curl -s -X POST "$BASE_URL/cash-sessions" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "opening_balance": 1000000
  }')
echo "$RESPONSE" | jq .
SESSION_ID=$(echo "$RESPONSE" | jq -r '.data.id')
echo -e "${GREEN}Session ID: $SESSION_ID${NC}"
echo ""

# Test 2: Get Current Cash Session
echo -e "${YELLOW}Test 2: Get Current Cash Session${NC}"
echo "GET $BASE_URL/cash-sessions/current"
curl -s -X GET "$BASE_URL/cash-sessions/current" \
  -H "Authorization: Bearer $TOKEN" | jq .
echo ""

# Test 3: Add Expense to Cash Session
echo -e "${YELLOW}Test 3: Add Expense to Cash Session${NC}"
echo "POST $BASE_URL/cash-sessions/$SESSION_ID/expenses"
curl -s -X POST "$BASE_URL/cash-sessions/$SESSION_ID/expenses" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 50000,
    "description": "Beli air mineral",
    "category": "Supplies"
  }' | jq .
echo ""

# Test 4: Get Cash Session Summary
echo -e "${YELLOW}Test 4: Get Cash Session Summary${NC}"
echo "GET $BASE_URL/cash-sessions/summary"
curl -s -X GET "$BASE_URL/cash-sessions/summary" \
  -H "Authorization: Bearer $TOKEN" | jq .
echo ""

# Test 5: Sales Recap
echo -e "${YELLOW}Test 5: Sales Recap${NC}"
START_DATE=$(date -v-7d +%Y-%m-%d)
END_DATE=$(date +%Y-%m-%d)
echo "GET $BASE_URL/reports/sales-recap?start_date=$START_DATE&end_date=$END_DATE"
curl -s -X GET "$BASE_URL/reports/sales-recap?start_date=$START_DATE&end_date=$END_DATE" \
  -H "Authorization: Bearer $TOKEN" | jq .
echo ""

# Test 6: Best Sellers
echo -e "${YELLOW}Test 6: Best Sellers${NC}"
echo "GET $BASE_URL/reports/best-sellers?start_date=$START_DATE&end_date=$END_DATE&limit=5"
curl -s -X GET "$BASE_URL/reports/best-sellers?start_date=$START_DATE&end_date=$END_DATE&limit=5" \
  -H "Authorization: Bearer $TOKEN" | jq .
echo ""

# Test 7: Sales Summary
echo -e "${YELLOW}Test 7: Sales Summary${NC}"
echo "GET $BASE_URL/reports/sales-summary?start_date=$START_DATE&end_date=$END_DATE"
curl -s -X GET "$BASE_URL/reports/sales-summary?start_date=$START_DATE&end_date=$END_DATE" \
  -H "Authorization: Bearer $TOKEN" | jq .
echo ""

# Test 8: Close Cash Session (only if session was created)
if [ ! -z "$SESSION_ID" ] && [ "$SESSION_ID" != "null" ]; then
    echo -e "${YELLOW}Test 8: Close Cash Session${NC}"
    echo "POST $BASE_URL/cash-sessions/$SESSION_ID/close"
    curl -s -X POST "$BASE_URL/cash-sessions/$SESSION_ID/close" \
      -H "Authorization: Bearer $TOKEN" \
      -H "Content-Type: application/json" \
      -d '{
        "closing_balance": 1450000
      }' | jq .
    echo ""
fi

echo "================================================"
echo -e "${GREEN}Testing Complete!${NC}"
echo "================================================"

