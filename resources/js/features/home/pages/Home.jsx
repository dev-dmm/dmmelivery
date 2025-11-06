import { Button } from "@/Components/ui/Button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/Components/ui/Card";
import { Link } from "@inertiajs/react";
import { Package, TrendingUp, Shield, Zap, Users, BarChart3 } from "lucide-react";

const Home = () => {
  const features = [
    {
      icon: Package,
      title: "Παρακολούθηση Αποστολών",
      description: "Παρακολουθήστε τις αποστολές σας σε πραγματικό χρόνο με λεπτομερείς πληροφορίες"
    },
    {
      icon: TrendingUp,
      title: "Αναλυτικά Στοιχεία",
      description: "Λάβετε εμπεριστατωμένα αναλυτικά στοιχεία για την απόδοση των αποστολών σας"
    },
    {
      icon: Shield,
      title: "Ασφάλεια",
      description: "Τα δεδομένα σας προστατεύονται με την υψηλότερη τεχνολογία ασφαλείας"
    },
    {
      icon: Zap,
      title: "Γρήγορη Ενσωμάτωση",
      description: "Ξεκινήστε να χρησιμοποιείτε την πλατφόρμα μας σε λίγα λεπτά"
    },
    {
      icon: Users,
      title: "Διαχείριση Πελατών",
      description: "Οργανώστε και διαχειριστείτε τους πελάτες σας με έξυπνα εργαλεία"
    },
    {
      icon: BarChart3,
      title: "Αναφορές",
      description: "Δημιουργήστε λεπτομερείς αναφορές για καλύτερη λήψη αποφάσεων"
    }
  ];

  return (
    <div className="min-h-screen bg-background">
      {/* Header */}
      <header className="border-b border-border bg-card">
        <div className="container mx-auto px-4 py-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-2">
              <Package className="h-8 w-8 text-primary" />
              <span className="text-2xl font-bold text-foreground">ShipTracker</span>
            </div>
            <nav className="hidden md:flex items-center space-x-6">
              <a href="#features" className="text-muted-foreground hover:text-foreground transition-colors">
                Χαρακτηριστικά
              </a>
              <Link href="/login">
                <Button variant="outline">Σύνδεση</Button>
              </Link>
            </nav>
          </div>
        </div>
      </header>

      {/* Hero Section */}
      <section className="py-20 px-4">
        <div className="container mx-auto text-center">
          <h1 className="text-4xl md:text-6xl font-bold text-foreground mb-6 animate-fade-in">
            Σύγχρονη Πλατφόρμα
            <br />
            <span className="text-primary">Παρακολούθησης Αποστολών</span>
          </h1>
          <p className="text-xl text-muted-foreground mb-8 max-w-2xl mx-auto animate-fade-in">
            Διαχειριστείτε τις αποστολές σας με ευκολία και παρακολουθήστε την απόδοση του καταστήματός σας 
            με την πιο προηγμένη πλατφόρμα στην Ελλάδα.
          </p>
          <div className="flex flex-col sm:flex-row gap-4 justify-center animate-fade-in">
            <Link href="/register">
              <Button size="lg" className="px-8 py-3 text-lg">
                Ξεκινήστε Τώρα
              </Button>
            </Link>
            <Button variant="outline" size="lg" className="px-8 py-3 text-lg">
              Μάθετε Περισσότερα
            </Button>
          </div>
        </div>
      </section>

      {/* Features Section */}
      <section id="features" className="py-20 px-4 bg-muted/30">
        <div className="container mx-auto">
          <div className="text-center mb-16">
            <h2 className="text-3xl md:text-4xl font-bold text-foreground mb-4">
              Γιατί να Επιλέξετε το ShipTracker;
            </h2>
            <p className="text-xl text-muted-foreground max-w-2xl mx-auto">
              Η πλατφόρμα μας προσφέρει όλα τα εργαλεία που χρειάζεστε για να διαχειριστείτε 
              τις αποστολές σας αποτελεσματικά.
            </p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            {features.map((feature, index) => (
              <Card key={index} className="hover:shadow-lg transition-shadow duration-300">
                <CardHeader>
                  <div className="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                    <feature.icon className="h-6 w-6 text-primary" />
                  </div>
                  <CardTitle className="text-xl">{feature.title}</CardTitle>
                </CardHeader>
                <CardContent>
                  <CardDescription className="text-base leading-relaxed">
                    {feature.description}
                  </CardDescription>
                </CardContent>
              </Card>
            ))}
          </div>
        </div>
      </section>

      {/* Stats Section */}
      <section className="py-20 px-4">
        <div className="container mx-auto">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-8 text-center">
            <div className="animate-fade-in">
              <div className="text-4xl md:text-5xl font-bold text-primary mb-2">10,000+</div>
              <div className="text-lg text-muted-foreground">Ενεργές Αποστολές</div>
            </div>
            <div className="animate-fade-in">
              <div className="text-4xl md:text-5xl font-bold text-primary mb-2">99.9%</div>
              <div className="text-lg text-muted-foreground">Διαθεσιμότητα</div>
            </div>
            <div className="animate-fade-in">
              <div className="text-4xl md:text-5xl font-bold text-primary mb-2">500+</div>
              <div className="text-lg text-muted-foreground">Ικανοποιημένοι Πελάτες</div>
            </div>
          </div>
        </div>
      </section>

      {/* CTA Section */}
      <section className="py-20 px-4 bg-primary">
        <div className="container mx-auto text-center">
          <h2 className="text-3xl md:text-4xl font-bold text-primary-foreground mb-6">
            Έτοιμοι να Ξεκινήσετε;
          </h2>
          <p className="text-xl text-primary-foreground/90 mb-8 max-w-2xl mx-auto">
            Εγγραφείτε σήμερα και ανακαλύψτε πώς μπορείτε να βελτιώσετε τη διαχείριση των αποστολών σας.
          </p>
          <Link href="/register">
            <Button size="lg" variant="secondary" className="px-8 py-3 text-lg">
              Ξεκινήστε Δωρεάν
            </Button>
          </Link>
        </div>
      </section>

      {/* Footer */}
      <footer className="py-12 px-4 bg-card border-t border-border">
        <div className="container mx-auto">
          <div className="grid grid-cols-1 md:grid-cols-4 gap-8">
            <div>
              <div className="flex items-center space-x-2 mb-4">
                <Package className="h-6 w-6 text-primary" />
                <span className="text-lg font-bold text-foreground">ShipTracker</span>
              </div>
              <p className="text-muted-foreground">
                Η καλύτερη πλατφόρμα παρακολούθησης αποστολών για το eShop σας.
              </p>
            </div>
            <div>
              <h3 className="font-semibold text-foreground mb-4">Προϊόν</h3>
              <ul className="space-y-2 text-muted-foreground">
                <li><a href="#" className="hover:text-foreground transition-colors">Χαρακτηριστικά</a></li>
                <li><a href="#" className="hover:text-foreground transition-colors">Τιμολόγιση</a></li>
                <li><a href="#" className="hover:text-foreground transition-colors">API</a></li>
              </ul>
            </div>
            <div>
              <h3 className="font-semibold text-foreground mb-4">Εταιρεία</h3>
              <ul className="space-y-2 text-muted-foreground">
                <li><a href="#" className="hover:text-foreground transition-colors">Σχετικά με εμάς</a></li>
                <li><a href="#" className="hover:text-foreground transition-colors">Επικοινωνία</a></li>
                <li><a href="#" className="hover:text-foreground transition-colors">Καριέρες</a></li>
              </ul>
            </div>
            <div>
              <h3 className="font-semibold text-foreground mb-4">Υποστήριξη</h3>
              <ul className="space-y-2 text-muted-foreground">
                <li><a href="#" className="hover:text-foreground transition-colors">Βοήθεια</a></li>
                <li><a href="#" className="hover:text-foreground transition-colors">Οδηγοί</a></li>
                <li><a href="#" className="hover:text-foreground transition-colors">Κοινότητα</a></li>
              </ul>
            </div>
          </div>
          <div className="border-t border-border mt-8 pt-8 text-center text-muted-foreground">
            <p>&copy; 2024 ShipTracker. Όλα τα δικαιώματα κατοχυρωμένα.</p>
          </div>
        </div>
      </footer>
    </div>
  );
};

export default Home;
