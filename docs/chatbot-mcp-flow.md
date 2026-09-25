# Chatbot to MCP Workflow

This document describes how a user query is interpreted and routed through the project chatbot, MCP server, and tool handlers.

## End-to-End Flow

SVG version: [chatbot-mcp-flow.svg](chatbot-mcp-flow.svg)

![Chatbot to MCP workflow](chatbot-mcp-flow.svg)

```mermaid
flowchart TD
    A[User submits query in chatbot tab] --> B[runProjectChatbot]
    B --> C[Validate project and query]
    C --> D[Interpret intent from query]
    D --> E[Resolve and normalize gene]
    E --> F[Build MCP arguments]
    F --> G[Call MCP initialize]
    G --> H[Call MCP tools call]
    H --> I[OncoServer routes to tool]
    I --> J[Tool returns structured redirect url]
    J --> K[Controller reads redirect url]
    K --> L[Redirect user to result page]

    D --> M[If needed use Gemini for intent]
    M --> E

    G --> N[If MCP initialize fails use fallback]
    H --> N
    J --> N
    N --> O[Fallback expression path]
    N --> P[Fallback mutation path]
    N --> Q[Fallback fusion path]
    N --> R[Fallback CNV path]
```

## Notes

- MCP invocation is attempted first for supported actions.
- Legacy fallback behavior is preserved if MCP initialize/tool call fails.
- Gene symbol correction uses exact match first, then fuzzy matching.
- Gemini parsing is used only when rule-based intent extraction does not match.
