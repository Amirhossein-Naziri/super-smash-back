// مثال استفاده از سیستم دعوت در فرانت‌اند

// 1. ثبت‌نام با کد دعوت
const registerWithReferral = async (userData, referralCode = null) => {
    try {
        const response = await fetch('/api/register', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                ...userData,
                referral_code: referralCode
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            console.log('ثبت‌نام موفق:', result.user);
            console.log('کد دعوت شما:', result.referral_code);
            
            if (result.referral_result) {
                console.log('نتیجه دعوت:', result.referral_result);
                if (result.referral_result.success) {
                    alert(`کد دعوت با موفقیت اعمال شد! ${result.referral_result.reward_amount} امتیاز دریافت کردید.`);
                } else {
                    alert(`خطا در کد دعوت: ${result.referral_result.message}`);
                }
            }
        } else {
            alert(`خطا در ثبت‌نام: ${result.error}`);
        }
    } catch (error) {
        console.error('خطا در ثبت‌نام:', error);
    }
};

// 2. اعتبارسنجی کد دعوت قبل از ثبت‌نام
const validateReferralCode = async (referralCode) => {
    try {
        const response = await fetch('/api/referral/validate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                referral_code: referralCode
            })
        });
        
        const result = await response.json();
        return result;
    } catch (error) {
        console.error('خطا در اعتبارسنجی:', error);
        return { valid: false, message: 'خطا در ارتباط با سرور' };
    }
};

// 3. دریافت آمار دعوت‌ها (نیاز به احراز هویت)
const getReferralStats = async (token) => {
    try {
        const response = await fetch('/api/referral/stats', {
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json',
            }
        });
        
        const result = await response.json();
        return result;
    } catch (error) {
        console.error('خطا در دریافت آمار:', error);
        return null;
    }
};

// 4. دریافت کد دعوت کاربر
const getMyReferralCode = async (token) => {
    try {
        const response = await fetch('/api/referral/code', {
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json',
            }
        });
        
        const result = await response.json();
        return result;
    } catch (error) {
        console.error('خطا در دریافت کد دعوت:', error);
        return null;
    }
};

// 5. دریافت لیست کاربران دعوت شده
const getReferredUsers = async (token) => {
    try {
        const response = await fetch('/api/referral/referred-users', {
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json',
            }
        });
        
        const result = await response.json();
        return result;
    } catch (error) {
        console.error('خطا در دریافت لیست دعوت‌ها:', error);
        return null;
    }
};

// 6. دریافت جدول رتبه‌بندی
const getLeaderboard = async (limit = 10) => {
    try {
        const response = await fetch(`/api/referral/leaderboard?limit=${limit}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            }
        });
        
        const result = await response.json();
        return result;
    } catch (error) {
        console.error('خطا در دریافت جدول رتبه‌بندی:', error);
        return null;
    }
};

// مثال استفاده در کامپوننت React
const ReferralComponent = () => {
    const [referralCode, setReferralCode] = useState('');
    const [myReferralCode, setMyReferralCode] = useState('');
    const [stats, setStats] = useState(null);
    const [leaderboard, setLeaderboard] = useState([]);
    const [token, setToken] = useState(localStorage.getItem('auth_token'));

    // دریافت کد دعوت کاربر
    useEffect(() => {
        if (token) {
            getMyReferralCode(token).then(result => {
                if (result && result.success) {
                    setMyReferralCode(result.referral_code);
                }
            });
        }
    }, [token]);

    // دریافت آمار دعوت‌ها
    useEffect(() => {
        if (token) {
            getReferralStats(token).then(result => {
                if (result && result.success) {
                    setStats(result.stats);
                }
            });
        }
    }, [token]);

    // دریافت جدول رتبه‌بندی
    useEffect(() => {
        getLeaderboard(10).then(result => {
            if (result && result.success) {
                setLeaderboard(result.leaderboard);
            }
        });
    }, []);

    // اعتبارسنجی کد دعوت
    const handleValidateReferralCode = async () => {
        if (!referralCode.trim()) {
            alert('لطفاً کد دعوت را وارد کنید');
            return;
        }

        const result = await validateReferralCode(referralCode);
        if (result.valid) {
            alert(`کد دعوت معتبر است! دعوت‌کننده: ${result.referrer_name}`);
        } else {
            alert(`کد دعوت نامعتبر: ${result.message}`);
        }
    };

    // کپی کردن کد دعوت
    const copyReferralCode = () => {
        navigator.clipboard.writeText(myReferralCode).then(() => {
            alert('کد دعوت کپی شد!');
        });
    };

    return (
        <div className="referral-system">
            <h2>سیستم دعوت</h2>
            
            {/* کد دعوت کاربر */}
            <div className="my-referral-code">
                <h3>کد دعوت شما</h3>
                <div className="code-display">
                    <input 
                        type="text" 
                        value={myReferralCode} 
                        readOnly 
                        className="referral-code-input"
                    />
                    <button onClick={copyReferralCode}>کپی</button>
                </div>
            </div>

            {/* اعتبارسنجی کد دعوت */}
            <div className="validate-referral">
                <h3>اعتبارسنجی کد دعوت</h3>
                <input 
                    type="text" 
                    value={referralCode}
                    onChange={(e) => setReferralCode(e.target.value)}
                    placeholder="کد دعوت را وارد کنید"
                    className="referral-input"
                />
                <button onClick={handleValidateReferralCode}>اعتبارسنجی</button>
            </div>

            {/* آمار دعوت‌ها */}
            {stats && (
                <div className="referral-stats">
                    <h3>آمار دعوت‌های شما</h3>
                    <div className="stats-grid">
                        <div className="stat-item">
                            <span className="stat-label">تعداد دعوت‌ها:</span>
                            <span className="stat-value">{stats.total_referrals}</span>
                        </div>
                        <div className="stat-item">
                            <span className="stat-label">مجموع پاداش‌ها:</span>
                            <span className="stat-value">{stats.total_rewards}</span>
                        </div>
                    </div>
                </div>
            )}

            {/* جدول رتبه‌بندی */}
            <div className="leaderboard">
                <h3>جدول رتبه‌بندی دعوت‌کنندگان</h3>
                <div className="leaderboard-list">
                    {leaderboard.map((user, index) => (
                        <div key={user.user_id} className="leaderboard-item">
                            <span className="rank">#{user.rank}</span>
                            <span className="name">{user.name}</span>
                            <span className="referrals">{user.referral_count} دعوت</span>
                            <span className="rewards">{user.referral_rewards} امتیاز</span>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
};

export default ReferralComponent;
