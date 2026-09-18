# Project Harness

Toda alteração de código deve usar o harness. O LangGraph é a autoridade do estado; não pule interrupts nem edite estado interno.

Fluxo MVP:

`plan → aprovação humana → execute → checks → review → repair (se necessário) → aprovação humana de commit → commit → merge na target branch`.

Use o agente `harness` como orquestrador. `harness-plan`, `harness-execute` e `harness-review` são workers de fase e devem seguir o ContextPacket produzido pelo harness, em vez de inventar outro workflow.
