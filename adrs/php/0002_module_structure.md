# Module structure

## Motivation

This ADR standardizes the design of different modules.
A module divides this project into smaller parts.
Each module defines a set of classes that belong together and are independently of classes in other modules.

## Decision

### Follow Package design

- Coupling is the degree of interdependence between software modules.
- Cohesion refers to how the elements of a module belong together.

We want to design components that are self-contained: independent, and with a single, well-defined purpose.
We stand for low coupling and high cohesion.

### Module standard design

The modules have a similar structure between each other. At the root of a module you can find:

- Facade: A Facade is the class that defines the entry point of the module.
  - There must be exactly one Facade per module.
  - If one module wants to interact with another module, they will do it via the Facade.

- Factory: A factory is a class where the instances of the module are created.
  - This is where the Dependency Injection (DI) happens.

- Other directories: they are part of the internal design of a particular module driven by its domain (DDD).
