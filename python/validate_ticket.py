import sys
import json
from datetime import datetime

# Redirect stderr to suppress warnings
sys.stderr = open('nul', 'w') if sys.platform == 'win32' else open('/dev/null', 'w')

try:
    import mysql.connector
except ImportError:
    sys.stderr = sys.__stderr__
    print(json.dumps({
        'success': False,
        'message': 'MySQL connector not installed. Run: py -m pip install mysql-connector-python'
    }))
    sys.exit(1)

# Restore stderr for error handling
sys.stderr = sys.__stderr__

DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'port': 3307,
    'password': '',
    'database': 'event_ticketing_db',
    'charset': 'utf8mb4',
    'autocommit': False
}

def validate_ticket(ticket_code):
    if not ticket_code or ticket_code.strip() == '':
        return {'success': False, 'message': 'Ticket code is required'}
    
    connection = None
    cursor = None
    
    try:
        connection = mysql.connector.connect(**DB_CONFIG)
        cursor = connection.cursor(dictionary=True)
        
        query = """
            SELECT 
                t.ticket_id, t.ticket_code, t.is_validated, t.validated_at,
                t.payment_status, t.purchase_date,
                a.attendee_id, a.first_name, a.last_name, a.email, a.attendee_type,
                e.event_id, e.event_name, e.event_date, e.start_time, e.status as event_status,
                tc.category_name, tc.price,
                v.venue_name
            FROM Ticket t
            JOIN Attendee a ON t.attendee_id = a.attendee_id
            JOIN Ticket_Category tc ON t.category_id = tc.category_id
            JOIN Event e ON tc.event_id = e.event_id
            JOIN Venue v ON e.venue_id = v.venue_id
            WHERE t.ticket_code = %s
        """
        
        cursor.execute(query, (ticket_code,))
        ticket = cursor.fetchone()
        
        if not ticket:
            return {'success': False, 'message': 'Invalid ticket code. Ticket not found.'}
        
        if ticket['is_validated']:
            return {
                'success': False,
                'already_validated': True,
                'message': 'This ticket has already been validated.',
                'validated_at': str(ticket['validated_at']) if ticket['validated_at'] else None,
                'ticket': {
                    'code': ticket['ticket_code'],
                    'attendee_name': f"{ticket['first_name']} {ticket['last_name']}",
                    'attendee_type': ticket['attendee_type'],
                    'event_name': ticket['event_name'],
                    'category_name': ticket['category_name']
                }
            }

        if ticket['payment_status'] == 'pending':
            return {
                'success': False,
                'message': 'Entry not allowed: Payment is still pending for this ticket.'
            }
        
        if ticket['event_status'] not in ['upcoming', 'ongoing']:
            return {
                'success': False,
                'message': f"Cannot validate ticket: Event is {ticket['event_status']}."
            }
        
        update_query = """
            UPDATE Ticket 
            SET is_validated = 1, validated_at = NOW() 
            WHERE ticket_id = %s
        """
        cursor.execute(update_query, (ticket['ticket_id'],))
        connection.commit()
        
        return {
            'success': True,
            'message': 'Ticket validated successfully! Entry granted.',
            'ticket': {
                'code': ticket['ticket_code'],
                'attendee_name': f"{ticket['first_name']} {ticket['last_name']}",
                'attendee_type': ticket['attendee_type'],
                'attendee_email': ticket['email'],
                'event_name': ticket['event_name'],
                'event_date': str(ticket['event_date']) if ticket['event_date'] else None,
                'start_time': str(ticket['start_time']) if ticket['start_time'] else None,
                'category_name': ticket['category_name'],
                'price': float(ticket['price']) if ticket['price'] else 0.00,
                'venue_name': ticket['venue_name'],
                'payment_status': ticket['payment_status'],
                'validated_at': datetime.now().strftime('%Y-%m-%d %H:%M:%S')
            }
        }
        
    except mysql.connector.Error as e:
        if connection:
            connection.rollback()
        return {'success': False, 'message': f'Database error: {str(e)}'}
    except Exception as e:
        if connection:
            connection.rollback()
        return {'success': False, 'message': f'Error: {str(e)}'}
    finally:
        if cursor:
            cursor.close()
        if connection:
            connection.close()

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({'success': False, 'message': 'Ticket code required'}))
        sys.exit(1)
    
    ticket_code = sys.argv[1].strip()
    result = validate_ticket(ticket_code)
    
    # Print ONLY JSON - ensure clean output
    output = json.dumps(result)
    sys.stdout.write(output)
    sys.stdout.flush()