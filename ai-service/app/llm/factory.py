"""LLM Provider Factory for dynamic provider selection in LAYA AI Service.

Provides a factory pattern implementation for creating and managing LLM
providers. Supports dynamic provider selection, registration, and
configuration-based default provider resolution.
"""

from typing import Optional

from app.config import settings
from app.llm.base import BaseLLMProvider
from app.llm.exceptions import LLMProviderError
from app.llm.providers import AnthropicProvider, OpenAIProvider


class LLMProviderFactory:
    """Factory for creating and managing LLM provider instances.

    Provides a centralized way to obtain LLM providers by name, manage
    provider registration, and resolve the default provider from
    configuration settings.

    Attributes:
        _providers: Registry of available provider classes by name
        _instances: Cache of instantiated provider instances

    Example:
        factory = LLMProviderFactory()

        # Get the default provider
        provider = factory.get_provider()

        # Get a specific provider
        openai = factory.get_provider("openai")
        anthropic = factory.get_provider("anthropic")

        # List available providers
        print(factory.available_providers)  # ['openai', 'anthropic']
    """

    def __init__(self) -> None:
        """Initialize the provider factory with registered providers."""
        self._providers: dict[str, type[BaseLLMProvider]] = {}
        self._instances: dict[str, BaseLLMProvider] = {}

        # Register built-in providers
        self._register_builtin_providers()

    def _register_builtin_providers(self) -> None:
        """Register the built-in LLM providers.

        Registers OpenAI and Anthropic providers as the default
        available providers.
        """
        self.register_provider("openai", OpenAIProvider)
        self.register_provider("anthropic", AnthropicProvider)

    def register_provider(
        self,
        name: str,
        provider_class: type[BaseLLMProvider],
    ) -> None:
        """Register a new LLM provider with the factory.

        Args:
            name: Unique identifier for the provider
            provider_class: The provider class to register

        Raises:
            ValueError: If name is empty or provider_class is not a valid
                        BaseLLMProvider subclass
        """
        if not name:
            raise ValueError("Provider name cannot be empty")

        if not issubclass(provider_class, BaseLLMProvider):
            raise ValueError(
                f"Provider class must be a subclass of BaseLLMProvider, "
                f"got {provider_class.__name__}"
            )

        self._providers[name] = provider_class

    def get_provider(
        self,
        name: Optional[str] = None,
        api_key: Optional[str] = None,
    ) -> BaseLLMProvider:
        """Get an LLM provider instance by name.

        Returns a cached instance if available, otherwise creates a new
        instance. If no name is provided, returns the default provider
        from settings.

        Args:
            name: Provider name (e.g., "openai", "anthropic").
                  If None, uses the default provider from settings.
            api_key: Optional API key override for the provider.
                     If provided, a new instance is created (not cached).

        Returns:
            BaseLLMProvider instance for the requested provider

        Raises:
            LLMProviderError: If the requested provider is not registered

        Example:
            factory = LLMProviderFactory()

            # Get default provider
            default = factory.get_provider()

            # Get specific provider
            openai = factory.get_provider("openai")

            # Get provider with custom API key (not cached)
            custom = factory.get_provider("openai", api_key="sk-custom...")
        """
        # Use default provider if name not specified
        provider_name = name or settings.llm_default_provider

        if provider_name not in self._providers:
            available = ", ".join(self._providers.keys())
            raise LLMProviderError(
                message=f"Unknown provider '{provider_name}'. "
                f"Available providers: {available}",
                provider=provider_name,
            )

        # If custom API key provided, create new instance (don't cache)
        if api_key is not None:
            provider_class = self._providers[provider_name]
            return provider_class(api_key=api_key)

        # Return cached instance or create new one
        if provider_name not in self._instances:
            provider_class = self._providers[provider_name]
            self._instances[provider_name] = provider_class()

        return self._instances[provider_name]

    def get_available_provider(self) -> Optional[BaseLLMProvider]:
        """Get the first available (configured) provider.

        Iterates through registered providers and returns the first one
        that is available (has valid API credentials configured).

        Returns:
            The first available provider, or None if no providers are available

        Example:
            factory = LLMProviderFactory()
            provider = factory.get_available_provider()
            if provider:
                response = await provider.complete(messages)
        """
        # First try the default provider
        default_name = settings.llm_default_provider
        if default_name in self._providers:
            provider = self.get_provider(default_name)
            if provider.is_available():
                return provider

        # Fall back to any available provider
        for name in self._providers:
            if name == default_name:
                continue  # Already tried
            provider = self.get_provider(name)
            if provider.is_available():
                return provider

        return None

    @property
    def available_providers(self) -> list[str]:
        """Get list of registered provider names.

        Returns:
            List of provider names that are registered with the factory

        Example:
            factory = LLMProviderFactory()
            print(factory.available_providers)  # ['openai', 'anthropic']
        """
        return list(self._providers.keys())

    @property
    def configured_providers(self) -> list[str]:
        """Get list of providers that are configured and available.

        Returns only providers that have valid API credentials configured
        and can be used for completions.

        Returns:
            List of provider names that are available for use

        Example:
            factory = LLMProviderFactory()
            print(factory.configured_providers)  # ['openai'] (if only OpenAI is configured)
        """
        configured = []
        for name in self._providers:
            provider = self.get_provider(name)
            if provider.is_available():
                configured.append(name)
        return configured

    def clear_cache(self) -> None:
        """Clear the provider instance cache.

        Forces new instances to be created on subsequent get_provider calls.
        Useful for testing or when configuration changes.
        """
        self._instances.clear()

    def __repr__(self) -> str:
        """Return string representation of the factory.

        Returns:
            String representation including registered providers
        """
        providers = ", ".join(self._providers.keys())
        return f"<LLMProviderFactory providers=[{providers}]>"
