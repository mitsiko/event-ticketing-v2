import sys
import json
from datetime import datetime

# Suppress extra output
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

sys.stderr = sys.__stderr__

DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'event_ticketing_db',
    'charset': 'utf8mb4'
}

def get_dashboard_report():
    connection = None
    cursor = None
    try:
        connection = mysql.connector.connect(**DB_CONFIG)
        cursor = connection.cursor(dictionary=True)
        stats = {}
        
        cursor.execute("SELECT COUNT(*) as total FROM Event")
        stats['total_events'] = cursor.fetchone()['total']
        
        cursor.execute("SELECT COUNT(*) as total FROM Event WHERE status = 'upcoming'")
        stats['upcoming_events'] = cursor.fetchone()['total']
        
        cursor.execute("SELECT COUNT(*) as total FROM Attendee")
        stats['total_attendees'] = cursor.fetchone()['total']
        
        cursor.execute("""
            SELECT attendee_type, COUNT(*) as count 
            FROM Attendee 
            GROUP BY attendee_type
        """)
        stats['attendees_by_type'] = cursor.fetchall()
        
        cursor.execute("SELECT COUNT(*) as total FROM Ticket")
        stats['total_tickets'] = cursor.fetchone()['total']
        
        cursor.execute("SELECT COUNT(*) as total FROM Ticket WHERE is_validated = 1")
        stats['validated_tickets'] = cursor.fetchone()['total']
        
        cursor.execute("""
            SELECT SUM(tc.price) as revenue 
            FROM Ticket t 
            JOIN Ticket_Category tc ON t.category_id = tc.category_id 
            WHERE t.payment_status = 'paid'
        """)
        result = cursor.fetchone()
        stats['total_revenue'] = float(result['revenue']) if result['revenue'] else 0.00
        
        cursor.execute("""
            SELECT event_type, COUNT(*) as count 
            FROM Event 
            GROUP BY event_type
        """)
        stats['events_by_type'] = cursor.fetchall()
        
        return {
            'success': True,
            'report_type': 'dashboard',
            'generated_at': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
            'data': stats
        }
    except Exception as e:
        return {'success': False, 'message': str(e)}
    finally:
        if cursor:
            cursor.close()
        if connection:
            connection.close()

def get_events_report(event_id=None):
    connection = None
    cursor = None
    try:
        connection = mysql.connector.connect(**DB_CONFIG)
        cursor = connection.cursor(dictionary=True)
        
        if event_id:
            query = """
                SELECT e.*, v.venue_name, o.org_name,
                       (SELECT SUM(total_slots) FROM Ticket_Category WHERE event_id = e.event_id) as total_slots,
                       (SELECT SUM(slots_remaining) FROM Ticket_Category WHERE event_id = e.event_id) as slots_remaining
                FROM Event e
                JOIN Venue v ON e.venue_id = v.venue_id
                JOIN Organization o ON e.org_id = o.org_id
                WHERE e.event_id = %s
            """
            cursor.execute(query, (event_id,))
        else:
            query = """
                SELECT e.*, v.venue_name, o.org_name,
                       (SELECT SUM(total_slots) FROM Ticket_Category WHERE event_id = e.event_id) as total_slots,
                       (SELECT SUM(slots_remaining) FROM Ticket_Category WHERE event_id = e.event_id) as slots_remaining
                FROM Event e
                JOIN Venue v ON e.venue_id = v.venue_id
                JOIN Organization o ON e.org_id = o.org_id
                ORDER BY e.event_date DESC
            """
            cursor.execute(query)
        
        events = cursor.fetchall()
        for event in events:
            if event.get('event_date'):
                event['event_date'] = str(event['event_date'])
            if event.get('start_time'):
                event['start_time'] = str(event['start_time'])
            if event.get('end_time'):
                event['end_time'] = str(event['end_time'])
        
        return {
            'success': True,
            'report_type': 'events',
            'event_id': event_id,
            'generated_at': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
            'data': events
        }
    except Exception as e:
        return {'success': False, 'message': str(e)}
    finally:
        if cursor:
            cursor.close()
        if connection:
            connection.close()

def get_attendees_report():
    connection = None
    cursor = None
    try:
        connection = mysql.connector.connect(**DB_CONFIG)
        cursor = connection.cursor(dictionary=True)
        
        cursor.execute("""
            SELECT 
                attendee_type,
                COUNT(*) as total,
                COUNT(CASE WHEN gender = 'male' THEN 1 END) as male,
                COUNT(CASE WHEN gender = 'female' THEN 1 END) as female,
                COUNT(CASE WHEN gender = 'non_binary' THEN 1 END) as non_binary,
                COUNT(CASE WHEN gender = 'prefer_not_to_say' THEN 1 END) as prefer_not_to_say
            FROM Attendee
            GROUP BY attendee_type
        """)
        stats = cursor.fetchall()
        
        cursor.execute("""
            SELECT attendee_id, first_name, last_name, email, attendee_type, registered_at
            FROM Attendee
            ORDER BY registered_at DESC
            LIMIT 10
        """)
        recent = cursor.fetchall()
        for r in recent:
            if r.get('registered_at'):
                r['registered_at'] = str(r['registered_at'])
        
        return {
            'success': True,
            'report_type': 'attendees',
            'generated_at': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
            'data': {
                'statistics': stats,
                'recent_registrations': recent
            }
        }
    except Exception as e:
        return {'success': False, 'message': str(e)}
    finally:
        if cursor:
            cursor.close()
        if connection:
            connection.close()

def get_tickets_report():
    connection = None
    cursor = None
    try:
        connection = mysql.connector.connect(**DB_CONFIG)
        cursor = connection.cursor(dictionary=True)
        
        cursor.execute("""
            SELECT 
                payment_status,
                COUNT(*) as total,
                COUNT(CASE WHEN is_validated = 1 THEN 1 END) as validated,
                COUNT(CASE WHEN is_validated = 0 THEN 1 END) as pending
            FROM Ticket
            GROUP BY payment_status
        """)
        payment_stats = cursor.fetchall()
        
        cursor.execute("""
            SELECT 
                e.event_name,
                COUNT(t.ticket_id) as tickets_sold,
                SUM(CASE WHEN t.is_validated = 1 THEN 1 ELSE 0 END) as tickets_validated
            FROM Ticket t
            JOIN Ticket_Category tc ON t.category_id = tc.category_id
            JOIN Event e ON tc.event_id = e.event_id
            GROUP BY e.event_id, e.event_name
            ORDER BY tickets_sold DESC
        """)
        event_stats = cursor.fetchall()
        
        return {
            'success': True,
            'report_type': 'tickets',
            'generated_at': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
            'data': {
                'payment_statistics': payment_stats,
                'event_statistics': event_stats
            }
        }
    except Exception as e:
        return {'success': False, 'message': str(e)}
    finally:
        if cursor:
            cursor.close()
        if connection:
            connection.close()

def get_revenue_report():
    connection = None
    cursor = None
    try:
        connection = mysql.connector.connect(**DB_CONFIG)
        cursor = connection.cursor(dictionary=True)
        
        cursor.execute("""
            SELECT 
                e.event_id,
                e.event_name,
                e.event_date,
                COUNT(t.ticket_id) as tickets_sold,
                SUM(tc.price) as revenue
            FROM Ticket t
            JOIN Ticket_Category tc ON t.category_id = tc.category_id
            JOIN Event e ON tc.event_id = e.event_id
            WHERE t.payment_status = 'paid'
            GROUP BY e.event_id, e.event_name, e.event_date
            ORDER BY revenue DESC
        """)
        revenue_by_event = cursor.fetchall()
        for r in revenue_by_event:
            if r.get('event_date'):
                r['event_date'] = str(r['event_date'])
            if r.get('revenue'):
                r['revenue'] = float(r['revenue'])
        
        cursor.execute("""
            SELECT 
                SUM(tc.price) as total_revenue,
                COUNT(t.ticket_id) as total_paid_tickets,
                AVG(tc.price) as average_ticket_price
            FROM Ticket t
            JOIN Ticket_Category tc ON t.category_id = tc.category_id
            WHERE t.payment_status = 'paid'
        """)
        summary = cursor.fetchone()
        if summary:
            summary['total_revenue'] = float(summary['total_revenue']) if summary['total_revenue'] else 0.00
            summary['average_ticket_price'] = float(summary['average_ticket_price']) if summary['average_ticket_price'] else 0.00
        
        return {
            'success': True,
            'report_type': 'revenue',
            'generated_at': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
            'data': {
                'summary': summary,
                'revenue_by_event': revenue_by_event
            }
        }
    except Exception as e:
        return {'success': False, 'message': str(e)}
    finally:
        if cursor:
            cursor.close()
        if connection:
            connection.close()

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({'success': False, 'message': 'Report type required'}))
        sys.exit(1)
    
    report_type = sys.argv[1].lower()
    event_id = sys.argv[2] if len(sys.argv) > 2 else None
    
    if report_type == 'dashboard':
        result = get_dashboard_report()
    elif report_type == 'events':
        result = get_events_report(event_id)
    elif report_type == 'attendees':
        result = get_attendees_report()
    elif report_type == 'tickets':
        result = get_tickets_report()
    elif report_type == 'revenue':
        result = get_revenue_report()
    else:
        result = {'success': False, 'message': f'Unknown report type: {report_type}'}
    
    output = json.dumps(result)
    sys.stdout.write(output)
    sys.stdout.flush()