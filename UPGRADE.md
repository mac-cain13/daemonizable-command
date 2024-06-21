# Upgrades

Important information about breaking changes and backward incompabilities

## 4.* to 5.0

### `EndlessContainerAwareCommand` removed

Since `ContainerAwareInterface` has been deprecated in Symfony 6.4, programmers are encouraged to use dependency injection instead in the constructor.

Therefore, it makes no sense to keep `EndlessContainerAwareCommand`. If you need to call `EntityManager::clear()` on your doctrine instance inside `EndlessCommand::finishIteration()`, you have to handle this in your code now.

If you need to access any service, inject it in the constructor of your derived class.