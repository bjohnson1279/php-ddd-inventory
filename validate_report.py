import sys

def validate_report(report_data, schema):
    """
    Validates a report against a given schema.

    :param report_data: List of dictionaries to validate.
    :param schema: Dictionary mapping keys to expected types.
    """
    errors = []

    for i, item in enumerate(report_data):
        for key, expected_type in schema.items():
            if key not in item:
                errors.append(f"Error: Missing key '{key}' in item at index {i}.")
                continue
            if not isinstance(item[key], expected_type):
                errors.append(f"Error: Key '{key}' in item {i} should be of type {expected_type.__name__}.")

    if errors:
        for error in errors:
            print(error)
        sys.exit(1)

if __name__ == "__main__":
    # Example usage / basic test
    schema = {
        "id": int,
        "name": str,
        "price": float
    }

    good_data = [
        {"id": 1, "name": "Apple", "price": 1.20},
        {"id": 2, "name": "Banana", "price": 0.50}
    ]

    bad_data = [
        {"id": 1, "name": "Apple"}, # missing price
        {"id": "2", "name": "Banana", "price": 0.50} # incorrect type for id
    ]

    print("Validating good data...")
    # This should pass without exiting
    validate_report(good_data, schema)
    print("Good data passed.")

    print("Validating bad data...")
    # This should exit with errors
    validate_report(bad_data, schema)
