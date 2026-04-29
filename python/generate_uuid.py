import uuid
import sys

if __name__ == "__main__":
    try:
        ticket_code = str(uuid.uuid4())
        # Print ONLY the UUID
        sys.stdout.write(ticket_code)
        sys.stdout.flush()
    except Exception:
        sys.exit(1)