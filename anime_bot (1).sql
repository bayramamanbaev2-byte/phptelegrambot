# database.py - Sizning MySQL strukturingizga mos PostgreSQL database

import os
import psycopg2
from psycopg2 import pool, Error, extras
from psycopg2.extras import RealDictCursor
import logging
from datetime import datetime
from dotenv import load_dotenv

# Environment variables
load_dotenv()

logging.basicConfig(
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    level=logging.INFO
)
logger = logging.getLogger(__name__)

class Database:
    _instance = None
    _pool = None
    
    def __new__(cls):
        if cls._instance is None:
            cls._instance = super(Database, cls).__new__(cls)
        return cls._instance
    
    def __init__(self):
        if not hasattr(self, '_initialized'):
            self._initialized = True
            self.setup_pool()
            self.create_tables()
            self.create_indexes()
    
    def get_db_config(self):
        """Database konfiguratsiyasini olish"""
        # Render.com PostgreSQL uchun
        if 'DATABASE_URL' in os.environ:
            return os.environ['DATABASE_URL']
        
        # Local development uchun
        return {
            'host': os.getenv('DB_HOST', 'localhost'),
            'database': os.getenv('DB_NAME', 'anime_bot'),
            'user': os.getenv('DB_USER', 'postgres'),
            'password': os.getenv('DB_PASSWORD', ''),
            'port': os.getenv('DB_PORT', '5432')
        }
    
    def setup_pool(self):
        """Connection pool ni sozlash"""
        try:
            db_config = self.get_db_config()
            
            if isinstance(db_config, str):  # DATABASE_URL formatida
                self._pool = pool.SimpleConnectionPool(
                    1, 20, db_config
                )
            else:  # Dictionary formatida
                self._pool = pool.SimpleConnectionPool(
                    1, 20,
                    host=db_config['host'],
                    database=db_config['database'],
                    user=db_config['user'],
                    password=db_config['password'],
                    port=db_config['port']
                )
            
            logger.info("✅ PostgreSQL connection pool yaratildi")
            
        except Error as e:
            logger.error(f"❌ Connection pool yaratishda xatolik: {e}")
            raise
    
    def get_connection(self):
        """Pool dan connection olish"""
        try:
            return self._pool.getconn()
        except Error as e:
            logger.error(f"❌ Connection olishda xatolik: {e}")
            return None
    
    def return_connection(self, connection):
        """Connection ni pool ga qaytarish"""
        try:
            self._pool.putconn(connection)
        except:
            pass
    
    def execute_query(self, query, params=None, fetch_one=False, fetch_all=False, commit=False, return_id=False):
        """SQL so'rovini bajarish"""
        connection = None
        cursor = None
        
        try:
            connection = self.get_connection()
            if not connection:
                return None
            
            cursor = connection.cursor(cursor_factory=RealDictCursor)
            
            if params:
                cursor.execute(query, params)
            else:
                cursor.execute(query)
            
            if commit:
                connection.commit()
            
            result = None
            if fetch_one:
                result = cursor.fetchone()
            elif fetch_all:
                result = cursor.fetchall()
            elif return_id:
                result = cursor.fetchone()
                if result and 'id' in result:
                    result = result['id']
                else:
                    # PostgreSQL da lastrowid yo'q, shuning uchun RETURNING ishlatamiz
                    result = cursor.fetchone()
                    if result and 'id' in result:
                        result = result['id']
            
            return result
            
        except Error as e:
            logger.error(f"❌ Query bajarishda xatolik: {e}")
            logger.error(f"Query: {query[:200]}...")
            if connection and commit:
                connection.rollback()
            return None
            
        finally:
            if cursor:
                cursor.close()
            if connection:
                self.return_connection(connection)
    
    def create_tables(self):
        """MySQL dump ga mos jadvallarni yaratish"""
        try:
            # 1. user_id jadvali - MySQL dagi bilan bir xil
            self.execute_query("""
                CREATE TABLE IF NOT EXISTS user_id (
                    id SERIAL PRIMARY KEY,
                    user_id VARCHAR(250) NOT NULL,
                    status TEXT NOT NULL DEFAULT 'Oddiy',
                    refid VARCHAR(11),
                    sana VARCHAR(250) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            """, commit=True)
            
            # 2. status jadvali - MySQL dagi bilan bir xil
            self.execute_query("""
                CREATE TABLE IF NOT EXISTS status (
                    id SERIAL PRIMARY KEY,
                    user_id VARCHAR(250) NOT NULL,
                    kun VARCHAR(250) NOT NULL,
                    date TEXT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            """, commit=True)
            
            # 3. kabinet jadvali - MySQL dagi bilan bir xil
            self.execute_query("""
                CREATE TABLE IF NOT EXISTS kabinet (
                    id SERIAL PRIMARY KEY,
                    user_id VARCHAR(250) NOT NULL,
                    pul VARCHAR(250) NOT NULL DEFAULT '0',
                    pul2 VARCHAR(250) NOT NULL DEFAULT '0',
                    odam VARCHAR(250) NOT NULL DEFAULT '0',
                    ban TEXT NOT NULL DEFAULT 'unban',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            """, commit=True)
            
            # 4. animelar jadvali - MySQL dagi bilan bir xil + like, deslike
            self.execute_query("""
                CREATE TABLE IF NOT EXISTS animelar (
                    id SERIAL PRIMARY KEY,
                    nom TEXT NOT NULL,
                    rams TEXT NOT NULL,
                    qismi TEXT NOT NULL,
                    davlat TEXT NOT NULL,
                    tili TEXT NOT NULL,
                    yili TEXT NOT NULL,
                    janri TEXT NOT NULL,
                    qidiruv INTEGER NOT NULL DEFAULT 0,
                    sana TEXT NOT NULL,
                    aniType TEXT,
                    like INTEGER DEFAULT 0,
                    deslike INTEGER DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            """, commit=True)
            
            # 5. anime_datas jadvali - MySQL dagi bilan bir xil
            self.execute_query("""
                CREATE TABLE IF NOT EXISTS anime_datas (
                    data_id SERIAL PRIMARY KEY,
                    id TEXT NOT NULL,
                    file_id TEXT NOT NULL,
                    qism TEXT NOT NULL,
                    sana TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            """, commit=True)
            
            # 6. channels jadvali - MySQL dagi bilan bir xil
            self.execute_query("""
                CREATE TABLE IF NOT EXISTS channels (
                    id SERIAL PRIMARY KEY,
                    channelId VARCHAR(32) NOT NULL,
                    channelType VARCHAR(255) NOT NULL,
                    channelLink VARCHAR(255) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            """, commit=True)
            
            # 7. joinRequests jadvali - MySQL dagi bilan bir xil
            self.execute_query("""
                CREATE TABLE IF NOT EXISTS joinRequests (
                    id SERIAL PRIMARY KEY,
                    channelId VARCHAR(32) NOT NULL,
                    userId VARCHAR(255) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            """, commit=True)
            
            # 8. send jadvali - MySQL dagi bilan bir xil
            self.execute_query("""
                CREATE TABLE IF NOT EXISTS send (
                    send_id SERIAL PRIMARY KEY,
                    time1 TEXT NOT NULL,
                    time2 TEXT NOT NULL,
                    start_id TEXT NOT NULL,
                    stop_id TEXT NOT NULL,
                    admin_id TEXT NOT NULL,
                    message_id TEXT NOT NULL,
                    reply_markup TEXT NOT NULL,
                    step TEXT NOT NULL,
                    time3 TEXT NOT NULL,
                    time4 TEXT NOT NULL,
                    time5 TEXT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            """, commit=True)
            
            logger.info("✅ MySQL strukturasiga mos barcha jadvallar yaratildi")
            
        except Error as e:
            logger.error(f"❌ Jadval yaratishda xatolik: {e}")
    
    def create_indexes(self):
        """Performance uchun indekslarni yaratish"""
        try:
            indexes = [
                "CREATE INDEX IF NOT EXISTS idx_user_id_user_id ON user_id(user_id)",
                "CREATE INDEX IF NOT EXISTS idx_user_id_refid ON user_id(refid)",
                "CREATE INDEX IF NOT EXISTS idx_status_user_id ON status(user_id)",
                "CREATE INDEX IF NOT EXISTS idx_kabinet_user_id ON kabinet(user_id)",
                "CREATE INDEX IF NOT EXISTS idx_kabinet_ban ON kabinet(ban)",
                "CREATE INDEX IF NOT EXISTS idx_animelar_nom ON animelar USING gin(to_tsvector('english', nom))",
                "CREATE INDEX IF NOT EXISTS idx_animelar_janri ON animelar USING gin(to_tsvector('english', janri))",
                "CREATE INDEX IF NOT EXISTS idx_animelar_qidiruv ON animelar(qidiruv)",
                "CREATE INDEX IF NOT EXISTS idx_anime_datas_id ON anime_datas(id)",
                "CREATE INDEX IF NOT EXISTS idx_channels_channelId ON channels(channelId)",
                "CREATE INDEX IF NOT EXISTS idx_joinRequests_userId ON joinRequests(userId)",
                "CREATE INDEX IF NOT EXISTS idx_joinRequests_channelId ON joinRequests(channelId)"
            ]
            
            for index_query in indexes:
                self.execute_query(index_query, commit=True)
            
            logger.info("✅ Indekslar muvaffaqiyatli yaratildi")
            
        except Error as e:
            logger.error(f"❌ Indeks yaratishda xatolik: {e}")
    
    # ==================== USER FUNCTIONS ====================
    
    def add_user(self, user_id, status='Oddiy', refid='0'):
        """Yangi foydalanuvchi qo'shish (MySQL bilan bir xil)"""
        try:
            sana = datetime.now().strftime('%d.%m.%Y')
            
            # user_id jadvaliga
            self.execute_query("""
                INSERT INTO user_id (user_id, status, refid, sana)
                VALUES (%s, %s, %s, %s)
                ON CONFLICT (user_id) DO NOTHING
            """, (user_id, status, refid, sana), commit=True)
            
            # kabinet jadvaliga
            self.execute_query("""
                INSERT INTO kabinet (user_id, pul, pul2, odam, ban)
                VALUES (%s, %s, %s, %s, %s)
                ON CONFLICT (user_id) DO NOTHING
            """, (user_id, '0', '0', '0', 'unban'), commit=True)
            
            logger.info(f"✅ Foydalanuvchi qo'shildi: {user_id}")
            return True
            
        except Error as e:
            logger.error(f"❌ Foydalanuvchi qo'shishda xatolik: {e}")
            return False
    
    def get_user(self, user_id):
        """Foydalanuvchi ma'lumotlarini olish"""
        query = """
            SELECT u.*, k.pul, k.pul2, k.odam, k.ban, s.kun, s.date
            FROM user_id u
            LEFT JOIN kabinet k ON u.user_id = k.user_id
            LEFT JOIN status s ON u.user_id = s.user_id
            WHERE u.user_id = %s
        """
        return self.execute_query(query, (user_id,), fetch_one=True)
    
    def get_user_status(self, user_id):
        """Foydalanuvchi statusini olish"""
        query = "SELECT status FROM user_id WHERE user_id = %s"
        result = self.execute_query(query, (user_id,), fetch_one=True)
        return result['status'] if result else 'Oddiy'
    
    def update_user_status(self, user_id, status):
        """Foydalanuvchi statusini yangilash"""
        query = "UPDATE user_id SET status = %s WHERE user_id = %s"
        return self.execute_query(query, (status, user_id), commit=True)
    
    # ==================== BALANCE FUNCTIONS ====================
    
    def get_balance(self, user_id):
        """Foydalanuvchi balansini olish"""
        query = "SELECT pul FROM kabinet WHERE user_id = %s"
        result = self.execute_query(query, (user_id,), fetch_one=True)
        return int(result['pul']) if result and result['pul'] else 0
    
    def update_balance(self, user_id, amount, operation='add'):
        """Foydalanuvchi balansini yangilash"""
        try:
            current_balance = self.get_balance(user_id)
            
            if operation == 'add':
                new_balance = current_balance + amount
            elif operation == 'subtract':
                new_balance = current_balance - amount
            elif operation == 'set':
                new_balance = amount
            else:
                new_balance = current_balance
            
            query = "UPDATE kabinet SET pul = %s WHERE user_id = %s"
            self.execute_query(query, (str(new_balance), user_id), commit=True)
            
            # pul2 ni ham yangilash (jami pul)
            if operation == 'add' or operation == 'set':
                query2 = "UPDATE kabinet SET pul2 = %s WHERE user_id = %s"
                self.execute_query(query2, (str(new_balance), user_id), commit=True)
            
            logger.info(f"✅ Balans yangilandi: {user_id} -> {new_balance}")
            return new_balance
            
        except Error as e:
            logger.error(f"❌ Balans yangilashda xatolik: {e}")
            return current_balance
    
    def update_referrals(self, user_id, increment=True):
        """Referral sonini yangilash"""
        try:
            if increment:
                query = "UPDATE kabinet SET odam = CAST(odam AS INTEGER) + 1 WHERE user_id = %s"
            else:
                query = "UPDATE kabinet SET odam = CAST(odam AS INTEGER) - 1 WHERE user_id = %s"
            
            self.execute_query(query, (user_id,), commit=True)
            return True
            
        except Error as e:
            logger.error(f"❌ Referral yangilashda xatolik: {e}")
            return False
    
    # ==================== VIP FUNCTIONS ====================
    
    def set_vip(self, user_id, days, date=None):
        """VIP statusini o'rnatish"""
        try:
            if not date:
                date = datetime.now().strftime('%d')
            
            # status jadvaliga qo'shish yoki yangilash
            query = """
                INSERT INTO status (user_id, kun, date)
                VALUES (%s, %s, %s)
                ON CONFLICT (user_id) 
                DO UPDATE SET kun = EXCLUDED.kun, date = EXCLUDED.date
            """
            self.execute_query(query, (user_id, str(days), date), commit=True)
            
            # user_id jadvalida statusni yangilash
            self.update_user_status(user_id, 'VIP')
            
            logger.info(f"✅ VIP o'rnatildi: {user_id} - {days} kun")
            return True
            
        except Error as e:
            logger.error(f"❌ VIP o'rnatishda xatolik: {e}")
            return False
    
    def extend_vip(self, user_id, additional_days):
        """VIP ni uzaytirish"""
        try:
            # Hozirgi VIP ma'lumotlarini olish
            query = "SELECT kun FROM status WHERE user_id = %s"
            result = self.execute_query(query, (user_id,), fetch_one=True)
            
            if result:
                current_days = int(result['kun'])
                new_days = current_days + additional_days
                date = datetime.now().strftime('%d')
                
                query = "UPDATE status SET kun = %s, date = %s WHERE user_id = %s"
                self.execute_query(query, (str(new_days), date, user_id), commit=True)
            else:
                self.set_vip(user_id, additional_days)
            
            logger.info(f"✅ VIP uzaytirildi: {user_id} +{additional_days} kun")
            return True
            
        except Error as e:
            logger.error(f"❌ VIP uzaytirishda xatolik: {e}")
            return False
    
    def get_vip_info(self, user_id):
        """VIP ma'lumotlarini olish"""
        query = "SELECT * FROM status WHERE user_id = %s"
        return self.execute_query(query, (user_id,), fetch_one=True)
    
    def remove_vip(self, user_id):
        """VIP dan chiqarish"""
        try:
            # status dan o'chirish
            query = "DELETE FROM status WHERE user_id = %s"
            self.execute_query(query, (user_id,), commit=True)
            
            # user_id da statusni yangilash
            self.update_user_status(user_id, 'Oddiy')
            
            logger.info(f"✅ VIP olib tashlandi: {user_id}")
            return True
            
        except Error as e:
            logger.error(f"❌ VIP olib tashlashda xatolik: {e}")
            return False
    
    # ==================== ANIME FUNCTIONS ====================
    
    def add_anime(self, anime_data):
        """Yangi anime qo'shish"""
        try:
            query = """
                INSERT INTO animelar (nom, rams, qismi, davlat, tili, yili, janri, qidiruv, sana, aniType, like, deslike)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                RETURNING id
            """
            params = (
                anime_data.get('nom', ''),
                anime_data.get('rams', ''),
                anime_data.get('qismi', ''),
                anime_data.get('davlat', ''),
                anime_data.get('tili', ''),
                anime_data.get('yili', ''),
                anime_data.get('janri', ''),
                0,  # qidiruv
                datetime.now().strftime('%H:%M %d.%m.%Y'),
                anime_data.get('aniType', ''),
                0,  # like
                0   # deslike
            )
            
            result = self.execute_query(query, params, return_id=True, commit=True)
            logger.info(f"✅ Anime qo'shildi: {anime_data.get('nom')} (ID: {result})")
            return result
            
        except Error as e:
            logger.error(f"❌ Anime qo'shishda xatolik: {e}")
            return None
    
    def get_anime(self, anime_id):
        """Anime ma'lumotlarini olish"""
        query = "SELECT * FROM animelar WHERE id = %s"
        return self.execute_query(query, (anime_id,), fetch_one=True)
    
    def update_anime_views(self, anime_id):
        """Anime ko'rishlar sonini oshirish"""
        query = "UPDATE animelar SET qidiruv = qidiruv + 1 WHERE id = %s"
        return self.execute_query(query, (anime_id,), commit=True)
    
    def update_anime_likes(self, anime_id, like=True):
        """Anime like/deslike ni yangilash"""
        if like:
            query = "UPDATE animelar SET like = like + 1 WHERE id = %s"
        else:
            query = "UPDATE animelar SET deslike = deslike + 1 WHERE id = %s"
        
        return self.execute_query(query, (anime_id,), commit=True)
    
    def search_anime_by_name(self, name, limit=10):
        """Nomi bo'yicha anime qidirish"""
        query = """
            SELECT * FROM animelar 
            WHERE nom ILIKE %s
            ORDER BY id DESC
            LIMIT %s
        """
        return self.execute_query(query, (f"%{name}%", limit), fetch_all=True)
    
    def search_anime_by_genre(self, genre, limit=10):
        """Janri bo'yicha anime qidirish"""
        query = """
            SELECT * FROM animelar 
            WHERE janri ILIKE %s
            ORDER BY id DESC
            LIMIT %s
        """
        return self.execute_query(query, (f"%{genre}%", limit), fetch_all=True)
    
    def get_recent_anime(self, limit=10):
        """So'ngi qo'shilgan animelar"""
        query = """
            SELECT * FROM animelar 
            ORDER BY id DESC
            LIMIT %s
        """
        return self.execute_query(query, (limit,), fetch_all=True)
    
    def get_top_viewed_anime(self, limit=10):
        """Eng ko'p ko'rilgan animelar"""
        query = """
            SELECT * FROM animelar 
            ORDER BY qidiruv DESC
            LIMIT %s
        """
        return self.execute_query(query, (limit,), fetch_all=True)
    
    def get_all_anime(self):
        """Barcha animelarni olish"""
        query = "SELECT id, nom, janri, qidiruv FROM animelar ORDER BY id"
        return self.execute_query(query, fetch_all=True)
    
    def update_anime(self, anime_id, field, value):
        """Animeni yangilash"""
        # Field ni xavfsiz qilish
        safe_fields = ['nom', 'rams', 'qismi', 'davlat', 'tili', 'yili', 'janri', 'aniType']
        if field not in safe_fields:
            return False
        
        query = f"UPDATE animelar SET {field} = %s WHERE id = %s"
        return self.execute_query(query, (value, anime_id), commit=True)
    
    def delete_anime(self, anime_id):
        """Animani o'chirish"""
        try:
            # Avval epizodlarni o'chirish
            self.execute_query("DELETE FROM anime_datas WHERE id = %s", (str(anime_id),), commit=True)
            
            # Keyin animeni o'chirish
            self.execute_query("DELETE FROM animelar WHERE id = %s", (anime_id,), commit=True)
            
            logger.info(f"✅ Anime o'chirildi: ID {anime_id}")
            return True
            
        except Error as e:
            logger.error(f"❌ Anime o'chirishda xatolik: {e}")
            return False
    
    # ==================== EPISODE FUNCTIONS ====================
    
    def add_episode(self, anime_id, file_id, qism):
        """Yangi epizod qo'shish"""
        try:
            query = """
                INSERT INTO anime_datas (id, file_id, qism, sana)
                VALUES (%s, %s, %s, %s)
                ON CONFLICT (id, qism) DO NOTHING
            """
            sana = datetime.now().strftime('%H:%M:%S %d.%m.%Y')
            self.execute_query(query, (str(anime_id), file_id, str(qism), sana), commit=True)
            
            logger.info(f"✅ Epizod qo'shildi: Anime {anime_id}, Qism {qism}")
            return True
            
        except Error as e:
            logger.error(f"❌ Epizod qo'shishda xatolik: {e}")
            return False
    
    def get_episode(self, anime_id, episode_number):
        """Epizod ma'lumotlarini olish"""
        query = "SELECT * FROM anime_datas WHERE id = %s AND qism = %s"
        return self.execute_query(query, (str(anime_id), str(episode_number)), fetch_one=True)
    
    def get_episodes(self, anime_id, offset=0, limit=25):
        """Anime epizodlarini olish"""
        query = """
            SELECT * FROM anime_datas 
            WHERE id = %s 
            ORDER BY CAST(qism AS INTEGER)
            OFFSET %s LIMIT %s
        """
        return self.execute_query(query, (str(anime_id), offset, limit), fetch_all=True)
    
    def get_total_episodes(self, anime_id):
        """Anime epizodlari sonini olish"""
        query = "SELECT COUNT(*) as count FROM anime_datas WHERE id = %s"
        result = self.execute_query(query, (str(anime_id),), fetch_one=True)
        return result['count'] if result else 0
    
    def update_episode(self, anime_id, episode_number, field, value):
        """Epizodni yangilash"""
        safe_fields = ['file_id', 'qism', 'sana']
        if field not in safe_fields:
            return False
        
        query = f"UPDATE anime_datas SET {field} = %s WHERE id = %s AND qism = %s"
        return self.execute_query(query, (value, str(anime_id), str(episode_number)), commit=True)
    
    def delete_episode(self, anime_id, episode_number):
        """Epizodni o'chirish"""
        query = "DELETE FROM anime_datas WHERE id = %s AND qism = %s"
        return self.execute_query(query, (str(anime_id), str(episode_number)), commit=True)
    
    # ==================== CHANNEL FUNCTIONS ====================
    
    def add_channel(self, channel_id, channel_type, channel_link):
        """Kanal qo'shish"""
        query = """
            INSERT INTO channels (channelId, channelType, channelLink)
            VALUES (%s, %s, %s)
            ON CONFLICT (channelId) 
            DO UPDATE SET channelType = EXCLUDED.channelType, channelLink = EXCLUDED.channelLink
        """
        return self.execute_query(query, (channel_id, channel_type, channel_link), commit=True)
    
    def get_channels(self):
        """Barcha kanallarni olish"""
        query = "SELECT * FROM channels ORDER BY id"
        return self.execute_query(query, fetch_all=True)
    
    def get_channel_by_id(self, channel_id):
        """Kanal ID bo'yicha olish"""
        query = "SELECT * FROM channels WHERE channelId = %s"
        return self.execute_query(query, (channel_id,), fetch_one=True)
    
    def delete_channel(self, channel_id):
        """Kanalni o'chirish"""
        query = "DELETE FROM channels WHERE channelId = %s"
        return self.execute_query(query, (channel_id,), commit=True)
    
    # ==================== JOIN REQUESTS FUNCTIONS ====================
    
    def add_join_request(self, channel_id, user_id):
        """Join request qo'shish"""
        query = """
            INSERT INTO joinRequests (channelId, userId)
            VALUES (%s, %s)
            ON CONFLICT (channelId, userId) DO NOTHING
        """
        return self.execute_query(query, (channel_id, user_id), commit=True)
    
    def has_join_request(self, channel_id, user_id):
        """Join request bormi tekshirish"""
        query = "SELECT * FROM joinRequests WHERE channelId = %s AND userId = %s"
        result = self.execute_query(query, (channel_id, user_id), fetch_one=True)
        return result is not None
    
    def delete_join_request(self, channel_id, user_id):
        """Join request ni o'chirish"""
        query = "DELETE FROM joinRequests WHERE channelId = %s AND userId = %s"
        return self.execute_query(query, (channel_id, user_id), commit=True)
    
    # ==================== BAN FUNCTIONS ====================
    
    def ban_user(self, user_id):
        """Foydalanuvchini ban qilish"""
        query = "UPDATE kabinet SET ban = 'ban' WHERE user_id = %s"
        return self.execute_query(query, (user_id,), commit=True)
    
    def unban_user(self, user_id):
        """Foydalanuvchidan ban olib tashlash"""
        query = "UPDATE kabinet SET ban = 'unban' WHERE user_id = %s"
        return self.execute_query(query, (user_id,), commit=True)
    
    def is_user_banned(self, user_id):
        """Foydalanuvchi banlanganligini tekshirish"""
        query = "SELECT ban FROM kabinet WHERE user_id = %s"
        result = self.execute_query(query, (user_id,), fetch_one=True)
        return result and result['ban'] == 'ban'
    
    # ==================== STATISTICS FUNCTIONS ====================
    
    def get_statistics(self):
        """Bot statistikasini olish"""
        try:
            stats = {}
            
            # Foydalanuvchilar soni
            result = self.execute_query("SELECT COUNT(*) as count FROM user_id", fetch_one=True)
            stats['total_users'] = result['count'] if result else 0
            
            # VIP foydalanuvchilar soni
            result = self.execute_query("SELECT COUNT(*) as count FROM status", fetch_one=True)
            stats['vip_users'] = result['count'] if result else 0
            
            # Animelar soni
            result = self.execute_query("SELECT COUNT(*) as count FROM animelar", fetch_one=True)
            stats['total_anime'] = result['count'] if result else 0
            
            # Epizodlar soni
            result = self.execute_query("SELECT COUNT(*) as count FROM anime_datas", fetch_one=True)
            stats['total_episodes'] = result['count'] if result else 0
            
            # Bugun qo'shilgan foydalanuvchilar
            today = datetime.now().strftime('%d.%m.%Y')
            result = self.execute_query(
                "SELECT COUNT(*) as count FROM user_id WHERE sana = %s", 
                (today,), fetch_one=True
            )
            stats['today_users'] = result['count'] if result else 0
            
            # Banlangan foydalanuvchilar
            result = self.execute_query(
                "SELECT COUNT(*) as count FROM kabinet WHERE ban = 'ban'", 
                fetch_one=True
            )
            stats['banned_users'] = result['count'] if result else 0
            
            return stats
            
        except Error as e:
            logger.error(f"❌ Statistika olishda xatolik: {e}")
            return {}
    
    def get_all_users(self, limit=1000):
        """Barcha foydalanuvchilarni olish (xabar yuborish uchun)"""
        query = "SELECT user_id FROM user_id ORDER BY id LIMIT %s"
        result = self.execute_query(query, (limit,), fetch_all=True)
        return [user['user_id'] for user in result] if result else []
    
    def get_users_count(self):
        """Foydalanuvchilar sonini olish"""
        query = "SELECT COUNT(*) as count FROM user_id"
        result = self.execute_query(query, fetch_one=True)
        return result['count'] if result else 0
    
    # ==================== SEND TABLE FUNCTIONS ====================
    
    def add_send_record(self, send_data):
        """Send jadvaliga yozuv qo'shish"""
        query = """
            INSERT INTO send (time1, time2, start_id, stop_id, admin_id, message_id, reply_markup, step, time3, time4, time5)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
        """
        params = (
            send_data.get('time1', ''),
            send_data.get('time2', ''),
            send_data.get('start_id', ''),
            send_data.get('stop_id', ''),
            send_data.get('admin_id', ''),
            send_data.get('message_id', ''),
            send_data.get('reply_markup', ''),
            send_data.get('step', ''),
            send_data.get('time3', ''),
            send_data.get('time4', ''),
            send_data.get('time5', '')
        )
        return self.execute_query(query, params, commit=True)
    
    def get_send_records(self):
        """Send jadvalidagi yozuvlarni olish"""
        query = "SELECT * FROM send ORDER BY send_id"
        return self.execute_query(query, fetch_all=True)
    
    def clear_send_table(self):
        """Send jadvalini tozalash"""
        query = "DELETE FROM send"
        return self.execute_query(query, commit=True)
    
    # ==================== MIGRATION FUNCTIONS ====================
    
    def migrate_vip_days(self):
        """VIP kunlarini yangilash (daily cron uchun)"""
        try:
            # Bugungi sana
            today = datetime.now().strftime('%d')
            
            # Kunlarini 1 ga kamaytirish
            query = """
                UPDATE status 
                SET kun = CAST(kun AS INTEGER) - 1,
                    date = %s
                WHERE CAST(kun AS INTEGER) > 0
            """
            self.execute_query(query, (today,), commit=True)
            
            # Kunlari 0 ga teng bo'lganlarni o'chirish
            query2 = """
                DELETE FROM status 
                WHERE CAST(kun AS INTEGER) <= 0
            """
            self.execute_query(query2, commit=True)
            
            # Statusni yangilash
            query3 = """
                UPDATE user_id u
                SET status = 'Oddiy'
                WHERE status = 'VIP' 
                AND NOT EXISTS (
                    SELECT 1 FROM status s 
                    WHERE s.user_id = u.user_id
                )
            """
            self.execute_query(query3, commit=True)
            
            logger.info("✅ VIP kunlari yangilandi")
            return True
            
        except Error as e:
            logger.error(f"❌ VIP migratsiyada xatolik: {e}")
            return False
    
    def cleanup_old_data(self, days=30):
        """Eski ma'lumotlarni tozalash"""
        try:
            cutoff_date = datetime.now().replace(tzinfo=None) - datetime.timedelta(days=days)
            
            # Eski join requests
            self.execute_query(
                "DELETE FROM joinRequests WHERE created_at < %s", 
                (cutoff_date,), commit=True
            )
            
            # Eski send records
            self.execute_query(
                "DELETE FROM send WHERE created_at < %s", 
                (cutoff_date,), commit=True
            )
            
            logger.info(f"✅ {days} kundan eski ma'lumotlar tozalandi")
            return True
            
        except Error as e:
            logger.error(f"❌ Ma'lumotlarni tozalashda xatolik: {e}")
            return False

# Global database obyekti
db = Database()

# Test qilish uchun
if __name__ == "__main__":
    print("🔧 MySQL to PostgreSQL Migration Test")
    print("=" * 50)
    
    try:
        # Statistikani ko'rish
        stats = db.get_statistics()
        print(f"📊 Foydalanuvchilar: {stats.get('total_users', 0)}")
        print(f"👑 VIP foydalanuvchilar: {stats.get('vip_users', 0)}")
        print(f"🎬 Animelar: {stats.get('total_anime', 0)}")
        print(f"🎥 Epizodlar: {stats.get('total_episodes', 0)}")
        print(f"📈 Bugun qo'shilgan: {stats.get('today_users', 0)}")
        print
