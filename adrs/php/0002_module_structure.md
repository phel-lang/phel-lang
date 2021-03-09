# Module structure

## Motivation

Standardize the modular design of the different modules

## Decision

The modules have a similar structure between each other. At the root of a module you can find: 

- Facade: it's the entry point of the module. 
  If another module wants to interact with another module, they will do it via the Facade.
  
- Factory: it's where the instances of that module are created. This is the place where 
  the Dependency Injection (DI) happens.
  
- Other directories: they are part of the internal design of a particular module driven by its domain (DDD).
