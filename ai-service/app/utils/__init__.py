"""Utility modules for LAYA AI Service."""

from app.utils.field_selection import (
    FieldSelector,
    filter_response,
    get_field_selector,
    parse_fields,
    validate_fields,
)
from app.utils.query_optimization import (
    eager_load_activity_participation_relationships,
    eager_load_activity_recommendation_relationships,
    eager_load_activity_relationships,
    eager_load_coaching_recommendation_relationships,
    eager_load_coaching_session_relationships,
    eager_load_evidence_source_relationships,
)

__all__ = [
    # Query optimization
    "eager_load_activity_relationships",
    "eager_load_activity_participation_relationships",
    "eager_load_activity_recommendation_relationships",
    "eager_load_coaching_session_relationships",
    "eager_load_coaching_recommendation_relationships",
    "eager_load_evidence_source_relationships",
    # Field selection
    "FieldSelector",
    "filter_response",
    "get_field_selector",
    "parse_fields",
    "validate_fields",
]
